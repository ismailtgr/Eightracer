<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Models\User;
use App\Services\Auth\GoogleIdentityVerifier;
use App\Services\Auth\SessionIssuer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class GoogleAuthController extends Controller
{
    public function challenge()
    {
        abort_unless(filled(config('prd.google_client_id')), 503);
        $id = bin2hex(random_bytes(24));
        $nonce = bin2hex(random_bytes(32));
        Cache::put('google-challenge:'.$id, $nonce, now()->addMinutes(5));

        return response()->json(['challenge_id' => $id, 'nonce' => $nonce, 'client_id' => config('prd.google_client_id'), 'expires_in' => 300]);
    }

    private function identity(Request $r, GoogleIdentityVerifier $verifier): array
    {
        $d = $r->validate(['challenge_id' => 'required|string|size:48', 'id_token' => 'required|string|max:8192']);

        return Cache::lock('google-lock:'.$d['challenge_id'], 15)->block(5, function () use ($d, $verifier) {
            $nonce = Cache::pull('google-challenge:'.$d['challenge_id']);
            abort_unless(is_string($nonce), 422);

            return $verifier->verify($d['id_token'], $nonce);
        });
    }

    public function login(Request $r, GoogleIdentityVerifier $verifier, SessionIssuer $sessions)
    {
        $r->validate(['nisn' => 'nullable|digits:10', 'role' => 'prohibited']);
        $identity = $this->identity($r, $verifier);
        $user = DB::transaction(function () use ($r, $identity) {
            $u = User::where('google_id', $identity['sub'])->lockForUpdate()->first();
            if ($u) {
                abort_unless($u->email === $identity['email'] && $u->email_verified_at, 403);

                return $u;
            }
            $existing = User::where('email', $identity['email'])->lockForUpdate()->first();
            if ($existing) {
                // Auto-link only while Google is authoritative for a verified existing email.
                abort_unless($existing->email_verified_at && ! $existing->google_id && $identity['authoritative'], 409, 'Login manual dahulu lalu hubungkan Google dari profil.');
                $existing->forceFill(['google_id' => $identity['sub']])->save();

                return $existing;
            }
            abort_unless($identity['authoritative'], 422, 'Daftar dan verifikasi email dahulu, lalu hubungkan Google dari profil.');
            $s = Siswa::where('nisn', $r->input('nisn'))->where('registration_email', $identity['email'])->whereNull('user_id')->lockForUpdate()->first();
            abort_unless($s && $r->filled('nisn'), 422, 'Data pendaftaran perlu diverifikasi admin sekolah.');
            $u = new User;
            $u->forceFill(['username' => $identity['email'], 'email' => $identity['email'], 'full_name' => $identity['name'] ?: $s->nama_lengkap,
                'google_id' => $identity['sub'], 'password' => null, 'role' => 'siswa', 'email_verified_at' => now()])->save();
            $s->user_id = $u->id;
            $s->save();

            return $u;
        });

        return response()->json($sessions->issue($user));
    }

    public function link(Request $r, GoogleIdentityVerifier $verifier)
    {
        $r->validate(['current_password' => 'required|string|max:1024']);
        $u = $r->user();
        abort_unless($u->password && Hash::check($r->input('current_password'), $u->password), 422);
        $identity = $this->identity($r, $verifier);
        DB::transaction(function () use ($u, $identity) {
            $u = User::whereKey($u->id)->lockForUpdate()->firstOrFail();
            abort_unless($u->email_verified_at && $u->email === $identity['email'], 422);
            abort_if(User::where('google_id', $identity['sub'])->whereKeyNot($u->id)->exists(), 409);
            $u->forceFill(['google_id' => $identity['sub']])->save();
        });

        return response()->json(['message' => 'Akun Google terhubung.']);
    }
}

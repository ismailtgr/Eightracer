<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Models\User;
use App\Services\Auth\OtpService;
use App\Services\Auth\SessionIssuer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class SchoolAuthController extends Controller
{
    public static function passwordRules(): array
    {
        return ['required', 'string', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols(),
            function ($attribute, $value, $fail) {
                if (strlen($value) > 72) {
                    $fail('Password maksimal 72 byte.');
                }
            }];
    }

    public function register(Request $r)
    {
        return response()->json(['message' => 'Registrasi mandiri ditutup. Akun siswa disiapkan oleh admin sekolah.'], 410);
    }

    public function verifyRegistration(Request $r)
    {
        return response()->json(['message' => 'Registrasi mandiri ditutup. Hubungi admin sekolah untuk akun siswa.'], 410);
    }

    public function login(Request $r, SessionIssuer $sessions)
    {
        $d = $r->validate(['email' => 'required_without:nisn|prohibits:nisn|nullable|email:rfc|max:255', 'nisn' => 'required_without:email|prohibits:email|nullable|digits:10', 'password' => 'required|string|max:1024', 'role' => 'prohibited']);
        $result = DB::transaction(function () use ($d, $sessions) {
            $q = User::query();
            if (! empty($d['nisn'])) {
                $q->where('role', 'siswa')->whereHas('siswa', fn ($s) => $s->where('nisn', $d['nisn']));
            } else {
                $q->where('email', mb_strtolower(trim($d['email'])));
            }
            $u = $q->lockForUpdate()->first();
            $hash = $u?->password ?? Hash::make(bin2hex(random_bytes(32)));
            if (! Hash::check($d['password'], $hash) || ! $u || ! $u->password) {
                throw ValidationException::withMessages(['email' => 'Email atau kata sandi salah.']);
            }
            if (Hash::needsRehash($u->password)) {
                $u->forceFill(['password' => Hash::make($d['password'])])->save();
            }

            return $sessions->issue($u);
        });

        return response()->json($result);
    }

    public function forgot(Request $r, OtpService $otp)
    {
        $d = $r->validate(['email' => 'required|email:rfc|max:255']);
        $email = mb_strtolower(trim($d['email']));
        $u = User::where('email', $email)->whereNotNull('email_verified_at')->first();
        $id = $otp->issue('password_reset', $email, [], $u?->id, null, $u !== null);

        return response()->json(['message' => 'Jika email akun terverifikasi, kode akan dikirim.', 'challenge_id' => $id, 'expires_in' => config('prd.otp_seconds')]);
    }

    public function verifyReset(Request $r, OtpService $otp)
    {
        $d = $r->validate(['challenge_id' => 'required|uuid', 'otp' => 'required|string|regex:/^[0-9]{6}$/']);
        $grant = $otp->consume($d['challenge_id'], 'password_reset', $d['otp'], function ($row) use ($otp) {
            abort_unless($row->user_id && User::whereKey($row->user_id)->where('email', $row->email)->whereNotNull('email_verified_at')->exists(), 422);
            $token = bin2hex(random_bytes(32));
            // Separate grant: consumed OTP cannot be replayed as a password-reset token.
            $id = (string) Str::uuid();
            DB::table('auth_challenges')->insert(['id' => $id, 'email' => $row->email, 'purpose' => 'reset_grant', 'user_id' => $row->user_id,
                'payload' => Crypt::encryptString('{}'), 'code_hash' => $otp->hash(bin2hex(random_bytes(32))),
                'grant_hash' => hash('sha256', $token), 'verified_at' => now(), 'expires_at' => now()->addSeconds(config('prd.reset_grant_seconds')), 'created_at' => now()]);

            return ['challenge_id' => $id, 'reset_token' => $token, 'expires_in' => config('prd.reset_grant_seconds')];
        });

        return response()->json($grant);
    }

    public function reset(Request $r, SessionIssuer $sessions)
    {
        $d = $r->validate(['challenge_id' => 'required|uuid', 'reset_token' => 'required|string|size:64', 'password' => self::passwordRules()]);
        DB::transaction(function () use ($d, $sessions) {
            $lookup = DB::table('auth_challenges')->where('id', $d['challenge_id'])->first();
            abort_unless($lookup && $lookup->purpose === 'reset_grant', 422, 'Token reset tidak valid.');
            $u = User::whereKey($lookup->user_id)->lockForUpdate()->firstOrFail();
            $row = DB::table('auth_challenges')->where('id', $d['challenge_id'])->lockForUpdate()->first();
            abort_unless($row && $row->purpose === 'reset_grant' && ! $row->consumed_at && Carbon::parse($row->expires_at)->isFuture()
                && hash_equals((string) $row->grant_hash, hash('sha256', $d['reset_token'])), 422, 'Token reset tidak valid.');
            abort_unless($u->email === $row->email && $u->email_verified_at, 422);
            $u->forceFill(['password' => Hash::make($d['password']), 'remember_token' => Str::random(60)])->save();
            $sessions->revoke($u);
            DB::table('auth_challenges')->where('email', $u->email)->whereIn('purpose', ['reset_grant', 'password_reset'])->update(['consumed_at' => now()]);
        });

        return response()->json(['message' => 'Kata sandi diperbarui. Silakan login kembali.']);
    }
}

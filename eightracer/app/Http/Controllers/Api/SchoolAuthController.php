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

    public function register(Request $r, OtpService $otp)
    {
        abort_if($r->input('role', 'siswa') !== 'siswa', 403);
        $d = $r->validate(['full_name' => 'required|string|max:255', 'email' => 'required|email:rfc|max:255',
            'nisn' => 'required|digits:10', 'password' => self::passwordRules(), 'role' => 'prohibited', 'user_id' => 'prohibited']);
        $email = mb_strtolower(trim($d['email']));
        $s = Siswa::where('nisn', $d['nisn'])->whereNull('user_id')->where('registration_email', $email)->first();
        if (! $s || User::where('email', $email)->exists()) {
            throw ValidationException::withMessages(['pendaftaran' => 'Data belum dapat didaftarkan. Hubungi admin sekolah untuk memeriksa NISN dan email pendaftaran.']);
        }
        $id = $otp->issue('register', $email, ['full_name' => $d['full_name'], 'password_hash' => Hash::make($d['password'])], null, $s->id);

        return response()->json(['message' => 'Verifikasi email untuk mengaktifkan akun.', 'challenge_id' => $id, 'expires_in' => config('prd.otp_seconds')], 202);
    }

    public function verifyRegistration(Request $r, OtpService $otp, SessionIssuer $sessions)
    {
        $d = $r->validate(['challenge_id' => 'required|uuid', 'otp' => 'required|string|regex:/^[0-9]{6}$/']);
        $user = $otp->consume($d['challenge_id'], 'register', $d['otp'], function ($row, $payload) {
            $s = Siswa::whereKey($row->siswa_id)->lockForUpdate()->first();
            abort_unless($s && ! $s->user_id && $s->registration_email === $row->email && ! User::where('email', $row->email)->exists(), 409);
            $u = new User;
            $u->forceFill(['username' => $row->email, 'email' => $row->email, 'full_name' => $payload['full_name'],
                'password' => $payload['password_hash'], 'role' => 'siswa', 'email_verified_at' => now()])->save();
            $s->user_id = $u->id;
            $s->save();

            return $u;
        });

        return response()->json($sessions->issue($user), 201);
    }

    public function login(Request $r, SessionIssuer $sessions)
    {
        $d = $r->validate(['email' => 'required|email:rfc|max:255', 'password' => 'required|string|max:1024', 'role' => 'prohibited']);
        $result = DB::transaction(function () use ($d, $sessions) {
            $u = User::where('email', mb_strtolower(trim($d['email'])))->lockForUpdate()->first();
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
            DB::table('auth_challenges')->where('email', $u->email)->whereIn('purpose',['reset_grant', 'password_reset'])->update(['consumed_at' => now()]);
        });

        return response()->json(['message' => 'Kata sandi diperbarui. Silakan login kembali.']);
    }
}

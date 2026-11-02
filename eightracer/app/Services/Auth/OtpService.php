<?php

namespace App\Services\Auth;

use App\Notifications\SchoolOtp;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OtpService
{
    public function ready(): void
    {
        abort_unless(config('prd.email_otp_enabled') && ! in_array(config('mail.default'), ['log', 'array', null], true)
            && ! (app()->isProduction() && config('security.mail_capture_only', false)), 503, 'Email verifikasi belum tersedia.');
    }

    public function issue(string $purpose, string $email, array $payload = [], ?int $userId = null, ?int $siswaId = null, bool $deliver = true): string
    {
        $this->ready();
        $id = (string) Str::uuid();
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        DB::transaction(function () use ($purpose, $email, $payload, $userId, $siswaId, $id, $code) {
            DB::table('auth_challenges')->where('email', $email)->where('purpose', $purpose)->whereNull('consumed_at')->update(['consumed_at' => now()]);
            DB::table('auth_challenges')->insert(['id' => $id, 'email' => $email, 'purpose' => $purpose, 'user_id' => $userId, 'siswa_id' => $siswaId,
                'payload' => Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR)), 'code_hash' => $this->hash($code),
                'attempts' => 0, 'expires_at' => now()->addSeconds(config('prd.otp_seconds')), 'created_at' => now()]);
        });
        if ($deliver) {
            try {
                Notification::route('mail', $email)->notify(new SchoolOtp($code, $purpose));
            } catch (\Throwable) {
                DB::table('auth_challenges')->where('id', $id)->update(['consumed_at' => now()]);
                abort(503, 'Email verifikasi belum dapat dikirim.');
            }
        }

        return $id;
    }

    public function consume(string $id, string $purpose, string $code, callable $callback, ?int $userId = null)
    {
        $result = DB::transaction(function () use ($id, $purpose, $code, $callback, $userId) {
            $row = DB::table('auth_challenges')->where('id', $id)->lockForUpdate()->first();
            if (! $row || $row->purpose !== $purpose || $row->consumed_at || $row->verified_at || Carbon::parse($row->expires_at)->isPast()
                || $row->attempts >= config('prd.otp_attempts') || ($userId !== null && (int) $row->user_id !== $userId)) {
                return ['invalid' => true];
            }
            if (! hash_equals($row->code_hash, $this->hash($code))) {
                DB::table('auth_challenges')->where('id', $id)->increment('attempts');

                return ['invalid' => true];
            }
            $value = $callback($row, json_decode(Crypt::decryptString($row->payload), true, 32, JSON_THROW_ON_ERROR));
            DB::table('auth_challenges')->where('id', $id)->update(['consumed_at' => now()]);

            return ['value' => $value];
        });
        if (isset($result['invalid'])) {
            throw ValidationException::withMessages(['otp' => 'Kode tidak valid, sudah digunakan, atau kedaluwarsa.']);
        }

        return $result['value'];
    }

    public function hash(string $value): string
    {
        return hash_hmac('sha256',$value,(string) config('app.key'));
    }
}

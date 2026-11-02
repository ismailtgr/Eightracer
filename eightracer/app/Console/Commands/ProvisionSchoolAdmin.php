<?php

namespace App\Console\Commands;

use App\Models\Administrator;
use App\Models\User;
use App\Services\Auth\SessionIssuer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class ProvisionSchoolAdmin extends Command
{
    protected $signature = 'eightracer:admin {email} {--name=} {--existing-id=} {--verify-email : Pengelola telah memverifikasi kepemilikan email di luar aplikasi}';

    protected $description = 'Menyediakan satu akun admin sekolah tanpa password bawaan atau pendaftaran publik';

    public function handle(): int
    {
        $email = mb_strtolower(trim($this->argument('email')));
        $name = $this->option('name');
        $v = Validator::make(['email' => $email, 'name' => $name], ['email' => 'required|email:rfc|max:255', 'name' => 'required|string|max:150']);
        if ($v->fails() || ! $this->option('verify-email')) {
            $this->error('Isi email/nama valid dan --verify-email setelah kepemilikan email benar-benar diperiksa.');

            return self::FAILURE;
        }
        $existing = $this->option('existing-id');
        $admin = User::where('role', 'administrator')->first();
        if ($admin && (! $existing || (int) $existing !== $admin->id)) {
            $this->error('Akun admin sudah tersedia. Gunakan --existing-id untuk melengkapi akun tersebut.');

            return self::FAILURE;
        }
        if ($existing && (! $admin || (int) $existing !== $admin->id)) {
            $this->error('ID bukan akun admin sekolah.');

            return self::FAILURE;
        }
        $password = null;
        if (! $admin) {
            $password = $this->secret('Kata sandi baru (minimal 12 karakter, huruf besar/kecil, angka dan simbol)');
            $confirmation = $this->secret('Ulangi kata sandi');
            $v = Validator::make(['password' => $password, 'password_confirmation' => $confirmation], ['password' => ['required', 'string', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols(), fn ($a, $value, $fail) => strlen($value) > 72 ? $fail('Terlalu panjang.') : null]]);
            if ($v->fails()) {
                $this->error('Kata sandi tidak memenuhi syarat atau konfirmasi berbeda.');

                return self::FAILURE;
            }
        }
        DB::transaction(function () use ($email, $name, $password, $admin) {
            abort_if(User::where('email', $email)->when($admin, fn ($q) => $q->whereKeyNot($admin->id))->exists(), 409, 'Email sudah digunakan.');
            $u = $admin ? User::whereKey($admin->id)->lockForUpdate()->firstOrFail() : new User;
            $attributes = ['email' => $email, 'email_verified_at' => now(), 'full_name' => $name];
            if (! $admin) {
                $attributes += ['username' => $email, 'password' => $password, 'role' => 'administrator'];
            }
            if ($admin && $admin->email !== $email) {
                $attributes['google_id'] = null;
                app(SessionIssuer::class)->revoke($u);
            }
            $u->forceFill($attributes)->save();
            Administrator::updateOrCreate(['user_id' => $u->id], ['nama_lengkap' => $name]);
        });
        $this->info('Akun admin siap. Password tidak ditampilkan atau disimpan di kode.');

        return self::SUCCESS;
    }
}

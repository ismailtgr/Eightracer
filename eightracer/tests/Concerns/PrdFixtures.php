<?php

namespace Tests\Concerns;

use App\Models\Kelas;
use App\Models\PerguruanTinggi;
use App\Models\Siswa;
use App\Models\StudyTracer;
use App\Models\User;
use App\Notifications\SchoolOtp;
use Illuminate\Support\Facades\Notification;

trait PrdFixtures
{
    private function account(string $role = 'siswa', array $extra = []): User
    {
        return User::forceCreate([...['username' => uniqid('prd-'), 'email' => uniqid('prd-').'@example.test', 'email_verified_at' => now(), 'password' => 'Strong-Test123!', 'role' => $role, 'full_name' => 'Akun Uji'], ...$extra]);
    }

    private function student(?User $u = null, array $extra = []): Siswa
    {
        return Siswa::create([...['user_id' => $u?->id, 'nisn' => (string) random_int(1000000000, 9999999999), 'nama_lengkap' => 'Murid Uji', 'jenis_kelamin' => 'P'], ...$extra]);
    }

    private function signIn(User $u): string
    {
        $this->app['auth']->forgetGuards();
        $token = $u->createToken('prd-test', ['*'], now()->addHour())->plainTextToken;
        $this->withToken($token);

        return $token;
    }

    private function mailReady(): void
    {
        config(['prd.email_otp_enabled' => true, 'mail.default' => 'smtp', 'cache.default' => 'array']);
        Notification::fake();
    }

    private function code(string $email, string $purpose): string
    {
        $code = null;
        Notification::assertSentOnDemand(SchoolOtp::class, function ($n, $channels, $notifiable) use (&$code, $email, $purpose) {
            if ($notifiable->routes['mail'] === $email && $n->purpose === $purpose) {
                $code = $n->code;

                return true;
            }

return false;
        });

        return $code;
    }

    private function classroom(Siswa $s): void
    {
        $c = Kelas::create(['tingkat' => 12, 'nama_kelas' => uniqid('XII-'), 'tahun_ajaran' => '2026/2027']);
        $s->riwayatKelas()->create(['kelas_id' => $c->id, 'tanggal_mulai' => today()->subMonth()->toDateString()]);
    }

    private function admission(Siswa $s, string $jalur = 'Mandiri'): StudyTracer
    {
        $pt = PerguruanTinggi::create(['nama_pt' => 'Universitas Uji', 'jenis_pt' => 'Negeri']);

        return StudyTracer::create(['siswa_id' => $s->id, 'perguruan_tinggi_id' => $pt->id, 'program_studi' => 'Informatika', 'strata_pendidikan' => 'S1', 'jalur_masuk' => $jalur, 'status_penerimaan' => 'diterima', 'tahun_masuk_pt' => 2026, 'kontak_wa_terbaru' => 'PRIVATE_PHONE']);
    }
}

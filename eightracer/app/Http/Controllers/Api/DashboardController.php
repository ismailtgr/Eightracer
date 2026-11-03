<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AlumniProgress;
use App\Models\ProgramEligibilitas;
use App\Models\Siswa;
use App\Models\StudyTracer;
use App\Services\Auth\SchoolAccess;
use App\Services\School\SchoolEligibility;
use App\Services\School\StudentProfile;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $r, SchoolEligibility $eligibility)
    {
        if (SchoolAccess::isStaff($r->user())) {
            $last = AlumniProgress::where('is_current', true)->orderByDesc('tanggal_update')->orderByDesc('id')->get()->unique('siswa_id');
            $last->load('alumniProgressBeasiswa');
            $programs = ProgramEligibilitas::where('is_active', true)->get();
            $eligibleCount = 0;
            if ($programs->isNotEmpty()) {
                Siswa::with(StudentProfile::RELATIONS)->chunkById(100, function ($students) use ($programs, $eligibility, &$eligibleCount) {
                    foreach ($students as $student) {
                        $profile = app(StudentProfile::class)->build($student);
                        foreach ($programs as $program) {
                            if ($eligibility->evaluate($student, $program, $profile)['status'] === 'Eligible') {
                                $eligibleCount++;
                                break;
                            }
                        }
                    }
                });
            }

            return response()->json(['data' => ['total_murid' => Siswa::count(), 'total_eligible' => $eligibleCount,
                'total_alumni_pt' => StudyTracer::where('status_penerimaan', 'diterima')->distinct('siswa_id')->count('siswa_id'),
                'total_penerima_kipk' => $last->where('penerima_kipk', true)->count(), 'total_penerima_beasiswa' => $last->filter(fn ($p) => $p->alumniProgressBeasiswa->where('status_beasiswa', 'diterima')->isNotEmpty())->count(),
                'catatan_eligible' => 'Dihitung dari data terkini dan program aktif pada saat permintaan.']]);
        }
        abort_unless($r->user()->role === 'siswa', 403);
        $s = $r->user()->siswa;
        abort_unless($s, 404);

        return response()->json(['data' => ['nama_lengkap' => $s->nama_lengkap, 'asesmen_terakhir' => $s->asesmenKarakteristik()->where('is_current', true)->latest('tanggal_asesmen')->first(),
            'eligibilitas' => ProgramEligibilitas::where('is_active', true)->get()->map(fn ($p) => $eligibility->studentResult($s, $p)),
            'jumlah_rekomendasi_terpublikasi' => $s->rekomendasiBeasiswa()->where('status_review', 'disetujui')->count()]]);
    }

    public function faq()
    {
        return response()->json(['data' => [
            ['question' => 'Bagaimana mendaftar?', 'answer' => 'Gunakan email yang dicatat sekolah dan NISN Anda, lalu verifikasi OTP. Hubungi admin jika data belum cocok.'],
            ['question' => 'Bagaimana admin login?', 'answer' => 'Akun admin disediakan pengelola sekolah. Login memakai email dan kata sandi; Google dapat dihubungkan melalui profil.'],
            ['question' => 'Siapa dapat mengubah data murid?', 'answer' => 'Data sekolah, asesmen, dan progres dikelola admin. Siswa mengelola akun sendiri dan melihat hasil miliknya.'],
            ['question' => 'Apakah rekomendasi menjamin diterima?', 'answer' => 'Tidak. Rekomendasi mengikuti data dan kriteria yang tersedia serta tinjauan admin. Keputusan akhir ada pada penyelenggara.'],
            ['question' => 'Bagaimana privasi alumni dijaga?', 'answer' => 'Jejak alumni ditampilkan berdasarkan izin publikasi. Kontak pribadi disembunyikan dan nominal hanya tampil bila diizinkan.'],
        ], 'contact_email' => config('prd.contact_email')]);
    }
}

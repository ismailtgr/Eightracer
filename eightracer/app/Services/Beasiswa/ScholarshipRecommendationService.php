<?php

namespace App\Services\Beasiswa;

use App\Models\BasisDataBeasiswa;
use App\Models\RekomendasiBeasiswa;
use App\Models\Siswa;
use Illuminate\Support\Facades\DB;

class ScholarshipRecommendationService
{
    public function __construct(private EligibilityEvaluator $evaluator, private GeminiScholarshipExplainer $gemini) {}

    public function sources(Siswa $siswa): array
    {
        $rapor = $siswa->nilaiRapor()->whereBetween('semester', [1, 5])->get();
        $semesterCount = $rapor->pluck('semester')->unique()->count();
        $parents = $siswa->orangTua()->get();
        $class = $siswa->riwayatKelas()->whereNull('tanggal_selesai')->with('kelas')->latest('tanggal_mulai')->first();
        // No inferred education level from age, name, or an arbitrary historical tracer row.
        $jenjang = $class && $class->kelas && in_array((int) $class->kelas->tingkat, [10, 11, 12], true) ? 'SMA' : null;
        $profile = [
            'jenjang' => $jenjang,
            'nilai_tka' => $siswa->nilai_tka,
            'rata_rapor' => $semesterCount === 5 ? $rapor->avg('nilai_akhir') : null,
            // Require both parent records and known income; missing is never zero.
            'penghasilan_orang_tua' => $parents->pluck('jenis_orang_tua')->unique()->count() === 2
                && $parents->every(fn ($p) => $p->penghasilan !== null) ? $parents->sum('penghasilan') : null,
            'persentase_kehadiran' => $siswa->rekapPresensi()->orderByDesc('semester')->value('persentase_kehadiran'),
            'jumlah_prestasi' => $siswa->prestasiMurid()->exists() ? $siswa->prestasiMurid()->count() : null,
            'status_eligible' => $siswa->hasilEligibilitas()->latest('tanggal_kalkulasi')->value('status_eligible'),
        ];
        $catalog = BasisDataBeasiswa::with('kriteriaKelayakanBeasiswa')->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('tanggal_tutup')->orWhereDate('tanggal_tutup', '>=', today()))
            ->orderBy('id')->get();

        return [$profile, $catalog, hash('sha256', json_encode([$profile, $catalog->toArray()], JSON_THROW_ON_ERROR))];
    }

    public function generate(Siswa $siswa): RekomendasiBeasiswa
    {
        [$profile, $catalog, $fingerprint] = $this->sources($siswa);
        abort_if($profile['jenjang'] === null, 422, 'Jenjang siswa belum diketahui. Lengkapi riwayat kelas aktif SMA.');
        $candidates = [];
        foreach ($catalog as $beasiswa) {
            if (! in_array($beasiswa->jenjang, [$profile['jenjang'], 'Keduanya'], true)) {
                continue;
            }
            $criteria = $beasiswa->kriteriaKelayakanBeasiswa->sortBy('id')->values()->toArray();
            $evaluation = $this->evaluator->evaluate($profile, $criteria);
            $snapshot = $beasiswa->only(['nama_beasiswa', 'jenjang', 'tanggal_buka', 'tanggal_tutup', 'link_pendaftaran', 'penyelenggara']);
            $candidates[] = ['beasiswa_id' => $beasiswa->id, 'beasiswa' => $snapshot, 'evaluasi' => $evaluation];
        }
        abort_if(count($candidates) === 0, 422, 'Belum ada beasiswa aktif yang sesuai jenjang.');
        usort($candidates, fn ($a, $b) => ($b['evaluasi']['skor'] <=> $a['evaluasi']['skor']) ?: ($a['beasiswa_id'] <=> $b['beasiswa_id']));
        $candidates = array_slice($candidates, 0, 20);
        $explanations = $this->gemini->explain($candidates);

        return DB::transaction(function () use ($siswa, $fingerprint, $candidates, $explanations) {
            $recommendation = RekomendasiBeasiswa::create([
                'siswa_id' => $siswa->id, 'tanggal_rekomendasi' => today(), 'status_review' => 'pending',
                'ai_model' => config('gemini.model'), 'prompt_version' => 'beasiswa-v1', 'source_fingerprint' => $fingerprint,
            ]);
            foreach ($candidates as $candidate) {
                $id = $candidate['beasiswa_id'];
                $recommendation->rekomendasiBeasiswaDetail()->create([
                    'beasiswa_id' => $id, 'skor_kecocokan' => $candidate['evaluasi']['skor'],
                    'is_recommended' => $candidate['evaluasi']['memenuhi'],
                    'alasan_rekomendasi' => $explanations[$id]['alasan'],
                    'persiapan' => $explanations[$id]['persiapan'],
                    'evaluasi_kriteria' => $candidate['evaluasi'], 'beasiswa_snapshot' => $candidate['beasiswa'],
                ]);
            }

            return $recommendation->load('rekomendasiBeasiswaDetail');
        });
    }
}

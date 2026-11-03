<?php

namespace App\Services\Beasiswa;

use App\Models\BasisDataBeasiswa;
use App\Models\ProgramEligibilitas;
use App\Models\RekomendasiBeasiswa;
use App\Models\Siswa;
use App\Services\School\SchoolEligibility;
use App\Services\School\StudentProfile;
use Illuminate\Support\Facades\DB;

class ScholarshipRecommendationService
{
    public function __construct(private EligibilityEvaluator $evaluator, private GeminiScholarshipExplainer $gemini,
        private ScholarshipContentRanker $ranker, private ScholarshipInterestProfile $interests) {}

    public function sources(Siswa $siswa, ?string $target = null): array
    {
        $profile = app(StudentProfile::class)->build($siswa);
        $profile['target_jenjang'] = $target ?? $profile['jenjang'];
        $profile['ml_interest_document'] = $this->interests->document($siswa);
        $profile['ml_ranking_version'] = [TfidfScholarshipModel::VERSION, (bool) config('scholarship_ml.enabled', true)];
        $profile['program_statuses'] = [];
        $programs = ProgramEligibilitas::where('is_active', true)->orderBy('id')->get();
        foreach ($programs as $p) {
            $result = app(SchoolEligibility::class)->evaluate($siswa, $p);
            $profile['program_statuses'][$p->id] = $result['status'] === 'Belum dinilai' ? null : $result['status'];
        }
        $catalog = BasisDataBeasiswa::with('kriteriaKelayakanBeasiswa')->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('tanggal_tutup')->orWhereDate('tanggal_tutup', '>=', ScholarshipSchedule::today()))->orderBy('id')->get();

        return [$profile, $catalog, hash('sha256', json_encode([$profile, $catalog->toArray(), $programs->toArray()], JSON_THROW_ON_ERROR))];
    }

    public function preview(Siswa $siswa, ?string $target = null): array
    {
        [$profile, $catalog, $fingerprint] = $this->sources($siswa, $target);
        abort_if($profile['target_jenjang'] === null, 422, 'Jenjang siswa belum diketahui. Lengkapi riwayat kelas aktif SMA.');
        $candidates = $documents = [];
        foreach ($catalog as $beasiswa) {
            if (! in_array($beasiswa->jenjang, [$profile['target_jenjang'], 'Keduanya'], true)) {
                continue;
            }
            $criteria = $beasiswa->kriteriaKelayakanBeasiswa->sortBy('id')->values()->toArray();
            $candidateProfile = $profile;
            if ($beasiswa->program_eligibilitas_id) {
                $candidateProfile['status_eligible'] = $profile['program_statuses'][$beasiswa->program_eligibilitas_id] ?? null;
                $program = ProgramEligibilitas::where('is_active', true)->find($beasiswa->program_eligibilitas_id);
                if ($program) {
                    $candidateProfile = app(SchoolEligibility::class)->programProfile($siswa, $program, $candidateProfile);
                }
            }
            $evaluation = $this->evaluator->evaluate($candidateProfile, $criteria);
            $snapshot = $beasiswa->only(['nama_beasiswa', 'jenjang', 'tanggal_buka', 'tanggal_tutup', 'link_pendaftaran', 'penyelenggara', 'durasi', 'satuan_durasi', 'periode_apply']);
            $snapshot['konteks'] = $profile['jenjang'] !== $profile['target_jenjang'] ? 'Persiapan jenjang berikutnya' : 'Jenjang saat ini';
            $candidates[] = ['beasiswa_id' => $beasiswa->id, 'beasiswa' => $snapshot, 'evaluasi' => $evaluation];
            // Descriptions are catalog content, not instructions, and stay inside the local ranker.
            $documents[$beasiswa->id] = (string) $beasiswa->deskripsi;
        }
        abort_if(count($candidates) === 0, 422, 'Belum ada beasiswa aktif yang sesuai jenjang.');
        [$candidates, $ml] = $this->ranker->rank($candidates, $documents, $profile['ml_interest_document']);
        $candidates = array_slice($candidates, 0, 20);

        return ['target_jenjang' => $profile['target_jenjang'], 'source_fingerprint' => $fingerprint,
            'pemeringkatan_ml' => $ml, 'kandidat' => $candidates];
    }

    public function generate(Siswa $siswa, ?string $target = null): RekomendasiBeasiswa
    {
        $preview = $this->preview($siswa, $target);
        $candidates = $preview['kandidat'];
        $providerCandidates = array_map(function ($candidate) {
            // Do not send interests, similarity, vocabulary, or model hashes to Gemini.
            unset($candidate['evaluasi']['pemeringkatan_ml']);

            return $candidate;
        }, $candidates);
        $explanations = $this->gemini->explain($providerCandidates);

        return DB::transaction(function () use ($siswa, $preview, $candidates, $explanations) {
            $recommendation = RekomendasiBeasiswa::create([
                'target_jenjang' => $preview['target_jenjang'], 'siswa_id' => $siswa->id, 'tanggal_rekomendasi' => today(), 'status_review' => 'pending',
                'ai_model' => config('gemini.model'), 'prompt_version' => 'beasiswa-v1', 'source_fingerprint' => $preview['source_fingerprint'],
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

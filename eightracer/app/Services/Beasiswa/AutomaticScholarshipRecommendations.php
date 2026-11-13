<?php

namespace App\Services\Beasiswa;

use App\Models\Siswa;
use Illuminate\Support\Facades\Cache;

/** Personal results are calculated from current sources, independent of editorial approval. */
class AutomaticScholarshipRecommendations
{
    public function __construct(private ScholarshipRecommendationService $recommendations, private GeminiScholarshipExplainer $gemini) {}

    public function forStudent(Siswa $student, ?string $target = null): array
    {
        if ($target === null) {
            [$profile, $catalog] = $this->recommendations->sources($student);
            $target = $profile['jenjang'] === 'SMA' && $catalog->contains(fn ($p) => in_array($p->jenjang, ['SMA', 'Keduanya'], true)) ? 'SMA' : 'Kuliah';
        }
        $preview = $this->recommendations->preview($student, $target);
        $key = 'scholarship-auto-v1:'.$student->id.':'.$preview['source_fingerprint'];
        $explanation = Cache::get($key);
        if (!is_array($explanation)) {
            $explanation = ['llm_used' => false, 'items' => []];
            $lock = Cache::lock($key.':lock', 25);
            if ($lock->get()) {
                try {
                    $candidates = array_map(function ($candidate) {
                        unset($candidate['evaluasi']['pemeringkatan_ml']);
                        return $candidate;
                    }, $preview['kandidat']);
                    try {
                        $explanation = ['llm_used' => true, 'items' => $this->gemini->explain($candidates, 15)];
                    } catch (\Throwable) {
                        // Keep the actual checklist/ranking available. Never invent an AI response or leak provider diagnostics.
                    }
                    Cache::put($key, $explanation, now()->addSeconds($explanation['llm_used'] ? 900 : 60));
                } finally { $lock->release(); }
            }
        }
        $items = array_map(function ($candidate) use ($explanation) {
            $candidate['evaluasi']['pemeringkatan_ml'] = array_intersect_key($candidate['evaluasi']['pemeringkatan_ml'],
                array_flip(['metode', 'status', 'alasan_status', 'skor_kemiripan', 'peringkat']));
            $evaluation = $candidate['evaluasi'];
            $reason = !$evaluation['lengkap'] ? 'Sebagian data atau persyaratan belum tersedia. Periksa rincian kriteria sebelum mendaftar.'
                : ($evaluation['memenuhi'] ? 'Data yang tersedia memenuhi kriteria yang tercatat. Hasil ini bukan jaminan penerimaan beasiswa.'
                : 'Ada kriteria yang belum terpenuhi. Periksa rinciannya dan pertimbangkan pilihan beasiswa lain.');
            $ai = $explanation['items'][$candidate['beasiswa_id']] ?? null;
            return $candidate + ['alasan' => $ai['alasan'] ?? $reason,
                'persiapan' => $ai['persiapan'] ?? ['Periksa persyaratan dan jadwal resmi.', 'Lengkapi data yang masih belum tersedia.']];
        }, $preview['kandidat']);
        return ['automatic' => true, 'data' => ['target_jenjang' => $preview['target_jenjang'],
            'kandidat' => $items, 'llm_used' => $explanation['llm_used'], 'pemeringkatan_ml' => array_intersect_key($preview['pemeringkatan_ml'],
                array_flip(['metode', 'status', 'alasan_status', 'catatan']))]];
    }
}

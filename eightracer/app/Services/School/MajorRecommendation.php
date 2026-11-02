<?php

namespace App\Services\School;

use App\Models\KlasterProdi;
use App\Models\RekomendasiProdi;
use App\Models\Siswa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MajorRecommendation
{
    public function sources(Siswa $s, int $semester): array
    {
        $grades = $s->nilaiRapor()->whereBetween('semester', [1, $semester])->orderBy('id')->get();
        $superseded = $s->asesmenKarakteristik()->whereNotNull('supersedes_id')->pluck('supersedes_id');
        $assessments = $s->asesmenKarakteristik()->whereNotIn('id', $superseded)->latest('tanggal_asesmen')->latest('id')->get();
        $riasecIds = $s->asesmenRiasec()->whereIn('asesmen_id', $assessments->pluck('id'))->pluck('asesmen_id');
        $assessment = $assessments->first(fn ($a) => $riasecIds->contains($a->id) || $a->kode_riasec !== null);
        $miGroups = $s->asesmenMultipleIntelligence()->whereIn('asesmen_id', $assessments->pluck('id'))->orderBy('id')->get()->groupBy('asesmen_id');
        $mi = collect();
        foreach ($assessments as $a) {
            if ($miGroups->has($a->id)) {
                $mi = $miGroups->get($a->id);
                break;
            }
        }
        if ($mi->isEmpty()) {
            $lastDate = $s->asesmenMultipleIntelligence()->whereNull('asesmen_id')->max('tanggal_asesmen');
            $mi = $lastDate ? $s->asesmenMultipleIntelligence()->whereNull('asesmen_id')->whereDate('tanggal_asesmen', $lastDate)->orderBy('id')->get() : collect();
        }
        $clusters = KlasterProdi::with('bobotMapelKlaster')->where('is_active', true)->orderBy('id')->get();

        return [$grades, $assessment, $mi, $clusters, hash('sha256', json_encode([$semester, $grades->toArray(), $assessment?->toArray(), $mi->toArray(), $clusters->toArray()], JSON_THROW_ON_ERROR))];
    }

    public function generate(Siswa $s, int $semester)
    {
        [$grades,$a,$mi,$clusters,$hash] = $this->sources($s, $semester);
        $rows = [];
        $batch = (string) Str::uuid();
        foreach ($clusters as $c) {
            $weights = $c->bobot_komponen;
            if (! $weights || abs(array_sum($weights) - 100) > 0.001) {
                continue;
            }
            $components = [];
            $complete = true;
            if (($weights['nilai'] ?? 0) > 0) {
                $subjectWeights = $c->bobotMapelKlaster;
                if ($subjectWeights->isEmpty() || abs($subjectWeights->sum('bobot_persentase') - 100) > 0.001) {
                    continue;
                }
                $score = 0;
                foreach ($subjectWeights as $sw) {
                    $g = $grades->where('mata_pelajaran_id', $sw->mata_pelajaran_id);
                    if ($g->pluck('semester')->unique()->count() !== $semester) {
                        $complete = false;
                        break;
                    }$score += $g->avg('nilai_akhir') * $sw->bobot_persentase / 100;
                }$components['nilai'] = $score;
            }
            if (($weights['riasec'] ?? 0) > 0) {
                $relevant = array_unique(str_split((string) $c->kode_riasec_relevan));
                $codes = str_split((string) $a?->kode_riasec);
                if (! $a || ! $a->kode_riasec || ! $c->kode_riasec_relevan) {
                    $complete = false;
                } else {
                    $components['riasec'] = 100 * count(array_intersect($relevant, $codes)) / count($relevant);
                }
            }
            if (($weights['multiple_intelligence'] ?? 0) > 0) {
                $relevant = array_filter(array_map('trim', explode(',', mb_strtolower((string) $c->kecerdasan_relevan))));
                $scores = $mi->filter(fn ($row) => in_array(mb_strtolower($row->jenis_kecerdasan), $relevant, true));
                if (! $relevant || $scores->count() !== count($relevant)) {
                    $complete = false;
                } else {
                    $components['multiple_intelligence'] = $scores->avg('skor');
                }
            }
            if (! $complete) {
                continue;
            }$total = 0;
            foreach ($components as $key => $value) {
                $total += $value * ($weights[$key] ?? 0) / 100;
            }
            $rows[] = ['siswa_id' => $s->id, 'klaster_id' => $c->id, 'semester' => $semester, 'batch_id' => $batch, 'status_review' => 'pending', 'source_fingerprint' => $hash,
                'nilai_rata_siswa' => $components['nilai'] ?? null, 'persentase_kesesuaian' => round($total, 2), 'alasan_rekomendasi' => 'Kecocokan dihitung dari komponen dan bobot klaster yang ditetapkan admin; bukan jaminan penerimaan.',
                'detail_penilaian' => $components, 'criteria_snapshot' => ['nama_klaster' => $c->nama_klaster, 'bobot_komponen' => $weights, 'mapel' => $c->bobotMapelKlaster->toArray(), 'kode_riasec_relevan' => $c->kode_riasec_relevan, 'kecerdasan_relevan' => $c->kecerdasan_relevan], 'tanggal_rekomendasi' => now()];
        }
        abort_unless(count($rows), 422, 'Lengkapi data dan bobot klaster terlebih dahulu.');

        return DB::transaction(fn () => collect($rows)->map(fn ($d) => RekomendasiProdi::create($d))->sortByDesc('persentase_kesesuaian')->values());
    }
}

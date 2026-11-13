<?php

namespace App\Services\Beasiswa;

class ScholarshipContentRanker
{
    public function __construct(private TfidfScholarshipModel $model) {}

    public function rank(array $candidates, array $documents, string $interests): array
    {
        $enabled = (bool) config('scholarship_ml.enabled', true);
        $trained = $enabled ? $this->model->fit($documents) : ['status' => 'disabled', 'documents' => 0, 'idf' => [], 'hash' => null];
        $scores = [];
        $reason = $trained['status'];
        if ($reason === 'ready') {
            $scores = $this->model->similarities($trained, $interests);
            $eligibleCandidates = array_filter($candidates, fn ($c) => ($c['evaluasi']['memenuhi'] ?? false) && ($c['evaluasi']['lengkap'] ?? false));
            $eligibleScores = array_map(fn ($c) => $scores[$c['beasiswa_id']] ?? 0, $eligibleCandidates);
            $reason = match (true) {
                trim($interests) === '' => 'profile_insufficient',
                count($eligibleScores) < 2 => 'no_eligible_alternatives',
                max($eligibleScores) <= 0 => 'no_eligible_overlap',
                max($eligibleScores) - min($eligibleScores) < 1e-8 => 'no_discriminative_signal',
                default => 'ready',
            };
        }
        $applied = $reason === 'ready';
        // Exploration can help incomplete profiles, but can never turn unknown facts into eligibility.
        $exploratoryIds = [];
        if (! $applied && $trained['status'] === 'ready' && trim($interests) !== '') {
            foreach ($candidates as $candidate) {
                $evaluation = $candidate['evaluasi'];
                if (! $evaluation['lengkap'] && ! in_array('belum_terpenuhi', array_column($evaluation['checklist'], 'status'), true)) {
                    $exploratoryIds[] = $candidate['beasiswa_id'];
                }
            }
            $exploratoryScores = array_map(fn ($id) => $scores[$id] ?? 0.0, $exploratoryIds);
            if (count($exploratoryScores) < 2 || max($exploratoryScores) <= 0 || max($exploratoryScores) - min($exploratoryScores) < 1e-8) {
                $exploratoryIds = [];
            }
        }
        foreach ($candidates as &$candidate) {
            $e = $candidate['evaluasi'];
            $eligible = ($e['memenuhi'] ?? false) && ($e['lengkap'] ?? false);
            $candidate['evaluasi']['pemeringkatan_ml'] = [
                'metode' => TfidfScholarshipModel::VERSION,
                'status' => ($applied && $eligible) || in_array($candidate['beasiswa_id'], $exploratoryIds, true) ? 'applied' : 'fallback',
                'alasan_status' => in_array($candidate['beasiswa_id'], $exploratoryIds, true) ? 'exploration_only' : (! $eligible ? (($e['lengkap'] ?? false) ? 'criteria_not_met' : 'criteria_incomplete') : $reason),
                'skor_kemiripan' => ($applied && $eligible) || in_array($candidate['beasiswa_id'], $exploratoryIds, true) ? ($scores[$candidate['beasiswa_id']] ?? 0.0) : null,
                'model_hash' => $trained['hash'], 'jumlah_dokumen' => $trained['documents'],
            ];
        }
        unset($candidate);
        usort($candidates, function ($a, $b) {
            $ae = $a['evaluasi'];
            $be = $b['evaluasi'];
            $eligible = (int) $be['memenuhi'] <=> (int) $ae['memenuhi'];
            if ($eligible !== 0) {
                return $eligible;
            }
            $exploration = (int) (($be['pemeringkatan_ml']['alasan_status'] ?? '') === 'exploration_only')
                <=> (int) (($ae['pemeringkatan_ml']['alasan_status'] ?? '') === 'exploration_only');
            if ($exploration !== 0) {
                return $exploration;
            }
            // Similarity may reorder eligible alternatives; it cannot change official requirements or scores.
            if (($ae['memenuhi'] && $be['memenuhi']) ||
                (($ae['pemeringkatan_ml']['alasan_status'] ?? '') === 'exploration_only' && ($be['pemeringkatan_ml']['alasan_status'] ?? '') === 'exploration_only')) {
                $ml = ($be['pemeringkatan_ml']['skor_kemiripan'] ?? 0) <=> ($ae['pemeringkatan_ml']['skor_kemiripan'] ?? 0);
                if ($ml !== 0) {
                    return $ml;
                }
            }

            return ($be['skor'] <=> $ae['skor']) ?: ($a['beasiswa_id'] <=> $b['beasiswa_id']);
        });
        foreach ($candidates as $i => &$candidate) {
            $candidate['evaluasi']['pemeringkatan_ml']['peringkat'] = $i + 1;
        }
        unset($candidate);

        return [$candidates, ['metode' => TfidfScholarshipModel::VERSION, 'status' => $applied || $exploratoryIds !== [] ? 'applied' : 'fallback',
            'alasan_status' => $exploratoryIds !== [] ? 'exploration_only' : $reason, 'jumlah_dokumen' => $trained['documents'], 'jumlah_fitur' => count($trained['idf']),
            'model_hash' => $trained['hash'], 'catatan' => 'Skor kemiripan 0–1 adalah relevansi isi; bukan peluang menerima beasiswa.']];
    }
}

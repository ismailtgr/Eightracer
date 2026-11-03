<?php

namespace Tests\Unit;

use App\Services\Beasiswa\ScholarshipContentRanker;
use App\Services\Beasiswa\TfidfScholarshipModel;
use Tests\TestCase;

class ScholarshipMachineLearningTest extends TestCase
{
    private function candidate(int $id, bool $eligible = true, float $score = 100, bool $complete = true): array
    {
        return ['beasiswa_id' => $id, 'evaluasi' => ['memenuhi' => $eligible, 'lengkap' => $complete, 'skor' => $score, 'checklist' => []]];
    }

    public function test_model_learns_idf_only_from_catalog_and_normalizes_cosine(): void
    {
        $model = new TfidfScholarshipModel;
        $trained = $model->fit([1 => 'olahraga bulu tangkis', 2 => 'olahraga basket']);
        $this->assertSame('ready', $trained['status']);
        $this->assertEqualsWithDelta(1.0, $trained['idf']['olahraga'], 1e-10);
        $this->assertEqualsWithDelta(log(3 / 2) + 1, $trained['idf']['basket'], 1e-10);
        $scores = $model->similarities($trained, 'olahraga bulu tangkis');
        $this->assertEqualsWithDelta(1.0, $scores[1], 1e-8);
        $this->assertGreaterThan($scores[2], $scores[1]);
        $model->similarities($trained, 'istilahyangtidakadalatihan');
        $this->assertArrayNotHasKey('istilahyangtidakadalatihan', $trained['idf']);
    }

    public function test_matching_content_reorders_eligible_alternatives_without_changing_criteria(): void
    {
        $c = [$this->candidate(1), $this->candidate(2)];
        [$ranked, $meta] = app(ScholarshipContentRanker::class)->rank($c, [1 => 'sains matematika fisika', 2 => 'olahraga bulu tangkis atlet'], 'bulu tangkis atlet');
        $this->assertSame('applied', $meta['status']);
        $this->assertSame([2, 1], array_column($ranked, 'beasiswa_id'));
        foreach ($ranked as $row) {
            $this->assertSame(100.0, $row['evaluasi']['skor']);
            $this->assertTrue($row['evaluasi']['memenuhi']);
        }
    }

    public function test_high_similarity_cannot_promote_failed_or_unknown_requirements(): void
    {
        $c = [$this->candidate(1, false, 50), $this->candidate(2), $this->candidate(3, false, 0, false)];
        [$ranked] = app(ScholarshipContentRanker::class)->rank($c, [1 => 'bulu tangkis atlet', 2 => 'matematika sains', 3 => 'bulu tangkis atlet'], 'bulu tangkis atlet');
        $this->assertSame(2, $ranked[0]['beasiswa_id']);
        $this->assertNull($ranked[1]['evaluasi']['pemeringkatan_ml']['skor_kemiripan']);
        $this->assertSame('criteria_incomplete', $ranked[2]['evaluasi']['pemeringkatan_ml']['alasan_status']);
    }

    public function test_empty_sparse_and_unknown_inputs_fall_back_without_fabricated_similarity(): void
    {
        $ranker = app(ScholarshipContentRanker::class);
        foreach ([
            [[], 'atlet', 'catalog_insufficient'],
            [[1 => 'atlet olahraga'], 'atlet', 'catalog_insufficient'],
            [[1 => 'beasiswa untuk siswa', 2 => 'dan atau yang'], 'atlet', 'catalog_insufficient'],
            [[1 => 'atlet olahraga', 2 => 'sains kimia'], '', 'profile_insufficient'],
            [[1 => 'atlet olahraga', 2 => 'sains kimia'], 'kosakatatidakdikenal', 'no_eligible_overlap'],
        ] as [$docs, $query, $reason]) {
            [$ranked, $meta] = $ranker->rank([$this->candidate(2), $this->candidate(1)], $docs, $query);
            $this->assertSame('fallback', $meta['status']);
            $this->assertSame($reason, $meta['alasan_status']);
            $this->assertSame([1, 2], array_column($ranked, 'beasiswa_id'));
            $this->assertNull($ranked[0]['evaluasi']['pemeringkatan_ml']['skor_kemiripan']);
        }
    }

    public function test_catalog_order_does_not_change_fitted_model_or_ranking(): void
    {
        $model = new TfidfScholarshipModel;
        $a = $model->fit([1 => 'matematika fisika', 2 => 'atlet olahraga']);
        $b = $model->fit([2 => 'atlet olahraga', 1 => 'matematika fisika']);
        $this->assertSame($a, $b);
    }

    public function test_equal_similarity_and_single_eligible_option_do_not_claim_personalized_ranking(): void
    {
        $ranker = app(ScholarshipContentRanker::class);
        [, $equal] = $ranker->rank([$this->candidate(1), $this->candidate(2)], [1 => 'bulu tangkis atlet', 2 => 'bulu tangkis atlet'], 'atlet');
        $this->assertSame('no_discriminative_signal', $equal['alasan_status']);
        [, $single] = $ranker->rank([$this->candidate(1), $this->candidate(2, false)], [1 => 'bulu tangkis atlet', 2 => 'matematika sains'], 'atlet');
        $this->assertSame('no_eligible_alternatives', $single['alasan_status']);
    }

    public function test_disabled_or_oversized_catalog_uses_baseline(): void
    {
        $ranker = app(ScholarshipContentRanker::class);
        config(['scholarship_ml.enabled' => false]);
        [, $disabled] = $ranker->rank([$this->candidate(1)], [1 => 'olahraga atlet', 2 => 'sains fisika'], 'atlet');
        $this->assertSame('disabled', $disabled['alasan_status']);
        config(['scholarship_ml.enabled' => true]);
        [, $oversized] = $ranker->rank([$this->candidate(1)], array_fill(1, 501, 'atlet olahraga'), 'atlet');
        $this->assertSame('catalog_limit', $oversized['alasan_status']);
    }
}

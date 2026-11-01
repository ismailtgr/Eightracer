<?php

namespace Tests\Unit;

use App\Services\Beasiswa\EligibilityEvaluator;
use PHPUnit\Framework\TestCase;

class ScholarshipEligibilityTest extends TestCase
{
    public function test_missing_and_unsupported_criteria_never_pass(): void
    {
        $evaluator = new EligibilityEvaluator;
        foreach (['penghasilan_orang_tua', 'field_tidak_dikenal'] as $field) {
            $result = $evaluator->evaluate([], [['tipe_kriteria' => $field, 'operator' => '<=', 'nilai_kriteria' => '1000000']]);
            $this->assertFalse($result['memenuhi']);
            $this->assertFalse($result['lengkap']);
            $this->assertSame('belum_diketahui', $result['checklist'][0]['status']);
        }
        $this->assertFalse($evaluator->evaluate([], [])['memenuhi']);
    }

    public function test_weighted_score_does_not_override_a_failed_requirement(): void
    {
        $result = (new EligibilityEvaluator)->evaluate(['nilai_tka' => 90, 'penghasilan_orang_tua' => 5000000], [
            ['tipe_kriteria' => 'nilai_tka', 'operator' => '>=', 'nilai_kriteria' => '80', 'bobot' => 9],
            ['tipe_kriteria' => 'penghasilan_orang_tua', 'operator' => '<=', 'nilai_kriteria' => '3000000', 'bobot' => 1],
        ]);
        $this->assertEquals(90, $result['skor']);
        $this->assertFalse($result['memenuhi']);
        $this->assertTrue($result['lengkap']);
    }
}

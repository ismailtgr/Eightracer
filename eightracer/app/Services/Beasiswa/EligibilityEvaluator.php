<?php

namespace App\Services\Beasiswa;

class EligibilityEvaluator
{
    public const FIELDS = ['nilai_tka', 'rata_rapor', 'penghasilan_orang_tua', 'persentase_kehadiran', 'jumlah_prestasi', 'status_eligible'];

    public function evaluate(array $profile, array $criteria): array
    {
        $checks = [];
        $total = $matched = 0;
        foreach ($criteria as $criterion) {
            $field = $criterion['tipe_kriteria'];
            $actual = $profile[$field] ?? null;
            $expected = $criterion['nilai_kriteria'];
            $operator = $criterion['operator'];
            $supported = in_array($field, self::FIELDS, true)
                && in_array($operator, ['=', '!=', '>=', '<=', '>', '<'], true);
            $numeric = $field !== 'status_eligible';
            $known = $supported && $actual !== null
                && (! $numeric || (is_numeric($actual) && is_numeric($expected)));
            if ($known) {
                $a = $numeric ? (float) $actual : (string) $actual;
                $b = $numeric ? (float) $expected : (string) $expected;
                $pass = match ($operator) {
                    '=' => $a === $b, '!=' => $a !== $b,
                    '>=' => $a >= $b, '<=' => $a <= $b,
                    '>' => $a > $b, '<' => $a < $b,
                };
            }
            $weight = max(0.01, (float) ($criterion['bobot'] ?? 1));
            $total += $weight;
            $matched += $known && $pass ? $weight : 0;
            $checks[] = [
                'kriteria' => $field, 'operator' => $operator, 'syarat' => $expected,
                'status' => ! $known ? 'belum_diketahui' : ($pass ? 'terpenuhi' : 'belum_terpenuhi'),
            ];
        }
        $statuses = array_column($checks, 'status');

        return [
            'skor' => $total ? round(100 * $matched / $total, 4) : 0,
            'lengkap' => count($checks) > 0 && ! in_array('belum_diketahui', $statuses, true),
            'memenuhi' => count($checks) > 0 && count(array_filter($statuses, fn ($s) => $s !== 'terpenuhi')) === 0,
            'checklist' => $checks,
        ];
    }
}

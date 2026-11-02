<?php

namespace App\Services\School;

use App\Services\Beasiswa\EligibilityEvaluator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class Criteria
{
    public static function validate(array $criteria, bool $allowStatus = true): array
    {
        $fields = $allowStatus ? EligibilityEvaluator::FIELDS : array_values(array_diff(EligibilityEvaluator::FIELDS, ['status_eligible', 'ipk', 'semester_kuliah']));
        $d = Validator::make(['kriteria' => $criteria], [
            'kriteria' => 'required|array|min:1|max:30', 'kriteria.*' => 'required|array:nama_kriteria,tipe_kriteria,operator,nilai_kriteria,bobot',
            'kriteria.*.nama_kriteria' => 'required|string|max:255', 'kriteria.*.tipe_kriteria' => ['required', Rule::in($fields)],
            'kriteria.*.operator' => 'required|in:=,!=,>=,<=,>,<', 'kriteria.*.nilai_kriteria' => 'required|string|max:255', 'kriteria.*.bobot' => 'nullable|numeric|between:0.01,999.99',
        ])->validate()['kriteria'];
        foreach ($d as $i => $row) {
            $status = $row['tipe_kriteria'] === 'status_eligible';
            if ($status && (! in_array($row['operator'], ['=', '!='], true) || ! in_array($row['nilai_kriteria'], ['Eligible', 'Tidak Eligible'], true))
                || ! $status && (! is_numeric($row['nilai_kriteria']) || ! is_finite((float) $row['nilai_kriteria']))) {
                throw ValidationException::withMessages(['kriteria.'.$i => 'Nilai kriteria tidak valid.']);
            }
            if (! $status) {
                $value = (float) $row['nilai_kriteria'];
                $bounds = match ($row['tipe_kriteria']) {
                    'nilai_tka', 'rata_rapor', 'persentase_kehadiran', 'skor_prestasi' => [0, 100],
                    'ipk' => [0, 4], 'semester_kuliah' => [1, 40],
                    'bantuan_sosial_sesuai' => [0, 1], 'poin_karakter' => [-1000000, 1000000],
                    default => [0, 9999999999999],
                };
                if ($value < $bounds[0] || $value > $bounds[1]) {
                    throw ValidationException::withMessages(['kriteria.'.$i.'.nilai_kriteria' => 'Ambang berada di luar rentang komponen.']);
                }
            }
        }

        return $d;
    }
}

<?php

namespace App\Services\School;

use App\Models\StudyTracer;

class AlumniAdmissionPath
{
    // These are source categories from the school's workbook, not inferred selection methods.
    public const SOURCE_LABELS = ['SWASTA' => 'Jalur Swasta', 'ADIK' => 'ADIK', 'UM PTKIN' => 'UM PTKIN', 'LUAR NEGERI' => 'Luar Negeri'];

    public function label(StudyTracer $record): ?string
    {
        if ($record->jalur_masuk !== 'Lainnya') return filled($record->jalur_masuk) ? $record->jalur_masuk : null;
        $notes = json_decode($record->siswa?->catatan ?? '{}', true);
        $labels = [];
        foreach (is_array($notes) ? ($notes['data_sumber'] ?? []) : [] as $source) {
            if (!is_array($source) || ($source['kind'] ?? '') !== 'admission') continue;
            $sheet = mb_strtoupper(trim($source['source']['sheet'] ?? ''));
            $label = self::SOURCE_LABELS[$sheet] ?? null;
            if (!$label || $this->program($source['program_original'] ?? '') !== $this->program($record->program_studi ?? '')) continue;
            if (isset($source['degree']) && $source['degree'] !== $record->strata_pendidikan) continue;
            if (isset($source['source']['year']) && $record->siswa?->tahun_lulus && (int) $source['source']['year'] !== (int) $record->siswa->tahun_lulus) continue;
            $labels[$label] = true;
        }
        return count($labels) === 1 ? array_key_first($labels) : null;
    }

    private function program(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', preg_replace('/^(?:S[1-3]|D[1-4])[\s.\-:]*/iu', '', mb_strtoupper(trim($value)))));
    }
}

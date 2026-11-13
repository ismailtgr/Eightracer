<?php

namespace App\Services\School;

use Illuminate\Support\Collection;

/** Observed alumni payments, never a university's official fee schedule. */
class TuitionRanges
{
    public function aggregate(Collection $admissions, Collection $current): array
    {
        $progress = $current->keyBy('study_tracer_id');
        $rows = $admissions->map(function ($admission) use ($progress) {
            $record = $progress->get($admission->id);
            // A mismatched foreign key must not attach another student's finances.
            if ($record && (int) $record->siswa_id !== (int) $admission->siswa_id) {
                $record = null;
            }

            return ['student_id' => $admission->siswa_id, 'university_id' => $admission->perguruan_tinggi_id,
                'university' => $admission->perguruanTinggi?->nama_pt ?? '-',
                'program' => $admission->program_studi ?? '-', 'has_progress' => $record !== null,
                'ukt' => $record?->nominal_ukt];
        });

        return ['by_university' => $this->groups($rows, 'university'),
            'by_program' => $this->groups($rows, 'program'),
            'data_source' => 'nominal_ukt_progres_alumni', 'official_fee_schedule' => false,
            'note' => 'Rentang nominal UKT yang dilaporkan alumni; bukan tarif resmi atau golongan UKT kampus. Nilai kosong tidak dianggap nol; KIP-K dan Bebas IPI tidak otomatis menetapkan UKT Rp0.'];
    }

    private function groups(Collection $rows, string $mode): Collection
    {
        return $rows->groupBy(fn ($row) => $mode === 'university' ? (string) $row['university_id'] : $row['program'])
            ->map(function ($group) use ($mode) {
                // One student can have several offers. Only the matched current admission carries UKT.
                $students = $group->sortByDesc('has_progress')->unique('student_id');
                $values = $students->pluck('ukt')->filter(fn ($value) => $value !== null)->map(fn ($value) => (float) $value);
                $known = $values->count();
                $first = $group->first();

                return ['university_id' => $mode === 'university' ? $first['university_id'] : null,
                    'university' => $mode === 'university' ? $first['university'] : null,
                    'program' => $mode === 'program' ? $first['program'] : null,
                    'count' => $students->count(), 'status' => $known ? 'tersedia' : 'belum_tercatat',
                    'stats' => ['known' => $known, 'unknown' => $students->count() - $known,
                        'minimum' => $known ? $values->min() : null,
                        'maximum' => $known ? $values->max() : null,
                        'mean' => $known ? round($values->avg(), 2) : null],
                    'span' => $known ? round($values->max() - $values->min(), 2) : null,
                    'golongan_minimum' => null, 'golongan_maximum' => null];
            })->sortBy(fn ($row) => $row[$mode])->values();
    }
}

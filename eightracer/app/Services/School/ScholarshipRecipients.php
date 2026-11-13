<?php

namespace App\Services\School;

use App\Models\SchoolScholarshipObservation;
use Illuminate\Database\Eloquent\Builder;

class ScholarshipRecipients
{
    /** Confirmed KIP source scope and accepted awards; never mere applications or synthetic profiles. */
    public function restrict(Builder $students): Builder
    {
        $sources = SchoolScholarshipObservation::whereNotNull('siswa_id')
            ->select(['siswa_id', 'nama_kolom', 'nilai_sumber'])->limit(50001)->get();
        abort_if($sources->count() > 50000, 422, 'Cakupan sumber penerima perlu diringkas terlebih dahulu.');
        $kipIds = $sources->filter(fn ($row) => SchoolScholarshipAnalytics::sourceProgram($row) === 'KIP/KIP-K')
            ->pluck('siswa_id')->unique()->values()->all();

        return LocalExampleRecords::exclude($students)->where(function ($q) use ($kipIds) {
            $q->whereIn('siswa.id', $kipIds)->orWhereHas('alumniProgress', function ($progress) {
                $progress->where('is_current', true)
                    ->whereHas('studyTracer', fn ($admission) => $admission->where('status_penerimaan', 'diterima')
                        ->whereColumn('study_tracer.siswa_id', 'alumni_progress.siswa_id'))
                    ->where(fn ($aid) => $aid->where('penerima_kipk', true)
                        ->orWhereHas('alumniProgressBeasiswa', fn ($award) => $award->where('status_beasiswa', 'diterima')));
            });
        });
    }
}

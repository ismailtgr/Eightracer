<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolScholarshipObservation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['indikator_kip' => 'boolean'];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function studyTracer(): BelongsTo
    {
        return $this->belongsTo(StudyTracer::class);
    }
}

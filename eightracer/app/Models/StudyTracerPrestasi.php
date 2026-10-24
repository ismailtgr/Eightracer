<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudyTracerPrestasi extends Model
{
    protected $table = 'study_tracer_prestasi';

    public $timestamps = false;

    protected $fillable = [
        'study_tracer_id',
        'prestasi_murid_id',
    ];

    public function studyTracer(): BelongsTo
    {
        return $this->belongsTo(StudyTracer::class, 'study_tracer_id');
    }

    public function prestasiMurid(): BelongsTo
    {
        return $this->belongsTo(PrestasiMurid::class, 'prestasi_murid_id');
    }
}

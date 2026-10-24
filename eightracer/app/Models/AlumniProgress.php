<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlumniProgress extends Model
{
    protected $table = 'alumni_progress';

    public $timestamps = false;

    protected $fillable = [
        'siswa_id',
        'study_tracer_id',
        'tahun_lulus',
        'status_pendidikan',
        'ipk_terkini',
        'tanggal_update',
    ];

    protected function casts(): array
    {
        return [
            'ipk_terkini' => 'decimal:2',
            'tanggal_update' => 'date:Y-m-d',
        ];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function studyTracer(): BelongsTo
    {
        return $this->belongsTo(StudyTracer::class, 'study_tracer_id');
    }

    public function alumniProgressBeasiswa(): HasMany
    {
        return $this->hasMany(AlumniProgressBeasiswa::class, 'alumni_progress_id');
    }

    public function beasiswa(): BelongsToMany
    {
        return $this->belongsToMany(BasisDataBeasiswa::class, 'alumni_progress_beasiswa', 'alumni_progress_id', 'beasiswa_id')->withPivot('status_beasiswa', 'nominal_beasiswa', 'nominal_ukt', 'periode');
    }
}

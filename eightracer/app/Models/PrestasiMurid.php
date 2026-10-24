<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrestasiMurid extends Model
{
    protected $table = 'prestasi_murid';

    public $timestamps = false;

    protected $fillable = [
        'siswa_id',
        'jenis_prestasi',
        'nama_kompetisi',
        'bidang',
        'tingkat',
        'peringkat',
        'bobot_persentase',
        'status_verifikasi',
        'penyelenggara',
        'tahun',
        'file_bukti_url',
    ];

    protected function casts(): array
    {
        return [
            'bobot_persentase' => 'decimal:2',
        ];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function studyTracerPrestasi(): HasMany
    {
        return $this->hasMany(StudyTracerPrestasi::class, 'prestasi_murid_id');
    }

    public function studyTracer(): BelongsToMany
    {
        return $this->belongsToMany(StudyTracer::class, 'study_tracer_prestasi', 'prestasi_murid_id', 'study_tracer_id');
    }
}

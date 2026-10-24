<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RekomendasiProdi extends Model
{
    protected $table = 'rekomendasi_prodi';

    public $timestamps = false;

    protected $fillable = [
        'siswa_id',
        'klaster_id',
        'study_tracer_id',
        'semester',
        'nilai_rata_siswa',
        'persentase_kesesuaian',
        'alasan_rekomendasi',
        'tanggal_rekomendasi',
    ];

    protected function casts(): array
    {
        return [
            'nilai_rata_siswa' => 'decimal:2',
            'persentase_kesesuaian' => 'decimal:2',
            'tanggal_rekomendasi' => 'datetime',
        ];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function klaster(): BelongsTo
    {
        return $this->belongsTo(KlasterProdi::class, 'klaster_id');
    }

    public function studyTracer(): BelongsTo
    {
        return $this->belongsTo(StudyTracer::class, 'study_tracer_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RekomendasiBeasiswaDetail extends Model
{
    use SoftDeletes;

    protected $attributes = ['is_hidden' => false];
    protected $table = 'rekomendasi_beasiswa_detail';

    public $timestamps = false;

    protected $fillable = [
        'rekomendasi_beasiswa_id',
        'beasiswa_id',
        'skor_kecocokan',
        'alasan_rekomendasi',
        'is_recommended',
        'evaluasi_kriteria',
        'beasiswa_snapshot',
        'persiapan', 'is_hidden', 'waktu_disarankan',
    ];

    protected function casts(): array
    {
        return [
            'skor_kecocokan' => 'decimal:4',
            'is_recommended' => 'boolean', 'is_hidden' => 'boolean',
            'evaluasi_kriteria' => 'array',
            'beasiswa_snapshot' => 'array',
            'persiapan' => 'array',
        ];
    }

    public function rekomendasiBeasiswa(): BelongsTo
    {
        return $this->belongsTo(RekomendasiBeasiswa::class, 'rekomendasi_beasiswa_id');
    }

    public function beasiswa(): BelongsTo
    {
        return $this->belongsTo(BasisDataBeasiswa::class, 'beasiswa_id');
    }
}

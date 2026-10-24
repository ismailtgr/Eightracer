<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AturanRekomendasiMapel extends Model
{
    protected $table = 'aturan_rekomendasi_mapel';

    public $timestamps = false;

    protected $fillable = [
        'mata_pelajaran_id',
        'indikator',
        'nilai_indikator',
        'bobot',
        'sumber_panduan',
        'keterangan',
        'aktif',
    ];

    protected function casts(): array
    {
        return [
            'bobot' => 'decimal:2',
            'aktif' => 'boolean',
        ];
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class, 'mata_pelajaran_id');
    }
}

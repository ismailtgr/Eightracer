<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KriteriaKelayakanBeasiswa extends Model
{
    protected $table = 'kriteria_kelayakan_beasiswa';

    public $timestamps = false;

    protected $fillable = [
        'beasiswa_id',
        'nama_kriteria',
        'tipe_kriteria',
        'operator',
        'nilai_kriteria',
        'bobot',
    ];

    protected function casts(): array
    {
        return [
            'bobot' => 'decimal:2',
        ];
    }

    public function beasiswa(): BelongsTo
    {
        return $this->belongsTo(BasisDataBeasiswa::class, 'beasiswa_id');
    }
}

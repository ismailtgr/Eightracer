<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsesmenMultipleIntelligence extends Model
{
    protected $table = 'asesmen_multiple_intelligence';

    public $timestamps = false;

    protected $fillable = [
        'siswa_id',
        'jenis_kecerdasan',
        'skor',
        'tanggal_asesmen',
    ];

    protected function casts(): array
    {
        return [
            'skor' => 'decimal:2',
            'tanggal_asesmen' => 'date:Y-m-d',
        ];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }
}

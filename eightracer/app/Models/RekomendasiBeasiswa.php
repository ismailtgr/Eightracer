<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RekomendasiBeasiswa extends Model
{
    protected $table = 'rekomendasi_beasiswa';

    public $timestamps = false;

    protected $fillable = [
        'siswa_id',
        'tanggal_rekomendasi',
        'status_review',
        'catatan_admin',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_rekomendasi' => 'date:Y-m-d',
        ];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function rekomendasiBeasiswaDetail(): HasMany
    {
        return $this->hasMany(RekomendasiBeasiswaDetail::class, 'rekomendasi_beasiswa_id');
    }
}

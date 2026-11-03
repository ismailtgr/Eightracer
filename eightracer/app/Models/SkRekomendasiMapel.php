<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SkRekomendasiMapel extends Model
{
    protected $table = 'sk_rekomendasi_mapel';

    protected $fillable = [
        'siswa_id',
        'guru_bk_id',
        'tahun_ajaran',
        'nomor_sk',
        'konten_surat',
        'status',
        'tanggal_terbit',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_terbit' => 'date:Y-m-d',
        ];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function guruBk(): BelongsTo
    {
        return $this->belongsTo(Guru::class, 'guru_bk_id');
    }
}

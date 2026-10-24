<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsesmenKarakteristik extends Model
{
    protected $table = 'asesmen_karakteristik';

    public $timestamps = false;

    protected $fillable = [
        'siswa_id',
        'tipe_kepribadian',
        'kecerdasan_majemuk_dominan',
        'kode_riasec',
        'tanggal_asesmen',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_asesmen' => 'datetime',
        ];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }
}

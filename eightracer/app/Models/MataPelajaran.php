<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MataPelajaran extends Model
{
    protected $table = 'mata_pelajaran';

    public $timestamps = false;

    protected $fillable = [
        'nama_mapel',
        'jenis_mapel',
        'kelompok',
        'tersedia_sebagai_pilihan',
        'deskripsi',
    ];

    protected function casts(): array
    {
        return [
            'tersedia_sebagai_pilihan' => 'boolean',
        ];
    }

    public function aturanRekomendasiMapel(): HasMany
    {
        return $this->hasMany(AturanRekomendasiMapel::class, 'mata_pelajaran_id');
    }

    public function rekomendasiMapel(): HasMany
    {
        return $this->hasMany(RekomendasiMapel::class, 'mata_pelajaran_id');
    }

    public function pilihanMapelSiswa(): HasMany
    {
        return $this->hasMany(PilihanMapelSiswa::class, 'mata_pelajaran_id');
    }

    public function nilaiRapor(): HasMany
    {
        return $this->hasMany(NilaiRapor::class, 'mata_pelajaran_id');
    }

    public function bobotMapelKlaster(): HasMany
    {
        return $this->hasMany(BobotMapelKlaster::class, 'mata_pelajaran_id');
    }
}

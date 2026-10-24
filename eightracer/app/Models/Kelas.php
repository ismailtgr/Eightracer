<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kelas extends Model
{
    protected $table = 'kelas';

    public $timestamps = false;

    protected $fillable = [
        'tingkat',
        'nama_kelas',
        'tahun_ajaran',
    ];

    public function riwayatKelas(): HasMany
    {
        return $this->hasMany(RiwayatKelas::class, 'kelas_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MinatProdiSiswa extends Model
{
    protected $table = 'minat_prodi_siswa';

    protected $fillable = [
        'siswa_id',
        'prioritas',
        'nama_prodi',
        'perguruan_tinggi',
    ];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }
}

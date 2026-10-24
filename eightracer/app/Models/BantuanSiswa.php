<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BantuanSiswa extends Model
{
    protected $table = 'bantuan_siswa';

    public $timestamps = false;

    protected $fillable = [
        'siswa_id',
        'jenis_bantuan_id',
        'nomor_penerima',
        'tanggal_mulai',
        'tanggal_selesai',
        'aktif',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date:Y-m-d',
            'tanggal_selesai' => 'date:Y-m-d',
            'aktif' => 'boolean',
        ];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function jenisBantuan(): BelongsTo
    {
        return $this->belongsTo(JenisBantuan::class, 'jenis_bantuan_id');
    }
}

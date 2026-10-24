<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatKelas extends Model
{
    protected $table = 'riwayat_kelas';

    public $timestamps = false;

    protected $fillable = [
        'siswa_id',
        'kelas_id',
        'tanggal_mulai',
        'tanggal_selesai',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date:Y-m-d',
            'tanggal_selesai' => 'date:Y-m-d',
        ];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }
}

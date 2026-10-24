<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RekapPresensi extends Model
{
    protected $table = 'rekap_presensi';

    public $timestamps = false;

    protected $fillable = [
        'siswa_id',
        'semester',
        'jumlah_hadir',
        'jumlah_sakit',
        'jumlah_izin',
        'jumlah_alpa',
        'persentase_kehadiran',
    ];

    protected function casts(): array
    {
        return [
            'persentase_kehadiran' => 'decimal:2',
        ];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }
}

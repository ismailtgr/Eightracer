<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatatanBimbingan extends Model
{
    protected $table = 'catatan_bimbingan';

    protected $fillable = [
        'siswa_id',
        'guru_bk_id',
        'mata_pelajaran_id',
        'konten',
        'strategi_belajar',
        'alokasi_jam_per_minggu',
        'status',
    ];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function guruBk(): BelongsTo
    {
        return $this->belongsTo(Guru::class, 'guru_bk_id');
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class, 'mata_pelajaran_id');
    }
}

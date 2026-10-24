<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PilihanMapelSiswa extends Model
{
    protected $table = 'pilihan_mapel_siswa';

    public $timestamps = false;

    protected $fillable = [
        'siswa_id',
        'mata_pelajaran_id',
        'tahun_ajaran',
        'tingkat_kelas',
        'status',
        'tanggal_pilihan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_pilihan' => 'date:Y-m-d',
        ];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class, 'mata_pelajaran_id');
    }
}

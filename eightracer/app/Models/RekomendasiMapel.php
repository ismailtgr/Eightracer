<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RekomendasiMapel extends Model
{
    protected $table = 'rekomendasi_mapel';

    public $timestamps = false;

    protected $fillable = [
        'siswa_id',
        'mata_pelajaran_id',
        'skor_rekomendasi',
        'tingkat_rekomendasi',
        'alasan_rekomendasi',
        'tanggal_rekomendasi',
        'tahun_ajaran',
        'status',
        'dikunci_oleh',
        'dikunci_pada',
        'probabilitas_penguasaan',
        'strategi_belajar',
        'fokus_penguatan_materi',
        'catatan_evaluasi_akademik',
    ];

    protected function casts(): array
    {
        return [
            'skor_rekomendasi'        => 'decimal:2',
            'tanggal_rekomendasi'     => 'date:Y-m-d',
            'probabilitas_penguasaan' => 'decimal:2',
            'dikunci_pada'            => 'datetime',
        ];
    }

    public function dikunciOleh(): BelongsTo
    {
        return $this->belongsTo(Guru::class, 'dikunci_oleh');
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

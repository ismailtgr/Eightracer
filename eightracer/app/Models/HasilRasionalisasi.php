<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HasilRasionalisasi extends Model
{
    protected $fillable = [
        'siswa_id',
        'program_studi_id',
        'periode_id',
        'pilihan_ke',
        'skor_rapor',
        'skor_mapel_pendukung',
        'skor_tka',
        'skor_prestasi',
        'skor_total',
        'peluang_persen',
        'kategori',
        'tanggal_kalkulasi',
    ];

    protected $casts = [
        'pilihan_ke' => 'integer',
        'skor_rapor' => 'decimal:4',
        'skor_mapel_pendukung' => 'decimal:4',
        'skor_tka' => 'decimal:4',
        'skor_prestasi' => 'decimal:4',
        'skor_total' => 'decimal:4',
        'peluang_persen' => 'decimal:2',
        'tanggal_kalkulasi' => 'datetime',
    ];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function programStudi(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class);
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(PeriodeEligibilitas::class, 'periode_id');
    }
}
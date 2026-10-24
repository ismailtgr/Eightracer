<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetailSkorEligibilitas extends Model
{
    protected $table = 'detail_skor_eligibilitas';

    public $timestamps = false;

    protected $fillable = [
        'hasil_id',
        'skor_raw_rapor',
        'skor_norm_rapor',
        'skor_raw_tka',
        'skor_norm_tka',
        'skor_raw_prestasi',
        'skor_norm_prestasi',
        'skor_raw_presensi',
        'skor_norm_presensi',
        'skor_raw_karakter',
        'skor_norm_karakter',
        'skor_raw_organisasi',
        'skor_norm_organisasi',
    ];

    protected function casts(): array
    {
        return [
            'skor_raw_rapor' => 'decimal:4',
            'skor_norm_rapor' => 'decimal:4',
            'skor_raw_tka' => 'decimal:4',
            'skor_norm_tka' => 'decimal:4',
            'skor_raw_prestasi' => 'decimal:4',
            'skor_norm_prestasi' => 'decimal:4',
            'skor_raw_presensi' => 'decimal:4',
            'skor_norm_presensi' => 'decimal:4',
            'skor_raw_karakter' => 'decimal:4',
            'skor_norm_karakter' => 'decimal:4',
            'skor_raw_organisasi' => 'decimal:4',
            'skor_norm_organisasi' => 'decimal:4',
        ];
    }

    public function hasil(): BelongsTo
    {
        return $this->belongsTo(HasilEligibilitas::class, 'hasil_id');
    }
}

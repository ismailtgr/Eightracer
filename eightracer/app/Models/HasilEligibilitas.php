<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class HasilEligibilitas extends Model
{
    protected $table = 'hasil_eligibilitas';

    public $timestamps = false;

    protected $fillable = [
        'siswa_id',
        'periode_id',
        'total_skor_akhir',
        'peringkat',
        'status_eligible',
        'tanggal_kalkulasi',
    ];

    protected function casts(): array
    {
        return [
            'total_skor_akhir' => 'decimal:4',
            'tanggal_kalkulasi' => 'datetime',
        ];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(PeriodeEligibilitas::class, 'periode_id');
    }

    public function detailSkorEligibilitas(): HasOne
    {
        return $this->hasOne(DetailSkorEligibilitas::class, 'hasil_id');
    }
}

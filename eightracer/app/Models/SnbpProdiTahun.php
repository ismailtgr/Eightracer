<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SnbpProdiTahun extends Model
{
    protected $fillable = [
        'program_studi_id',
        'tahun',
        'daya_tampung_snbp',
        'peminat_snbp',
        'rata_rata_lolos',
        'min_lolos',
        'jumlah_sampel_alumni',
        'sumber_ambang',
    ];

    protected $casts = [
        'tahun' => 'integer',
        'daya_tampung_snbp' => 'integer',
        'peminat_snbp' => 'integer',
        'rata_rata_lolos' => 'decimal:2',
        'min_lolos' => 'decimal:2',
        'jumlah_sampel_alumni' => 'integer',
    ];

    public function programStudi(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class);
    }

    public function getKeketatanAttribute(): ?float
    {
        if (empty($this->daya_tampung_snbp) || empty($this->peminat_snbp)) {
            return null;
        }

        return round($this->peminat_snbp / $this->daya_tampung_snbp, 2);
    }
}
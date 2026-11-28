<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramStudi extends Model
{
    protected $fillable = [
        'perguruan_tinggi_id',
        'pddikti_prodi_id',
        'nama_prodi',
        'kode_prodi',
        'jenjang',
        'akreditasi_prodi',
        'klaster_id',
        'jumlah_mahasiswa',
        'daya_tampung_total',
        'tahun_data',
    ];

    public function perguruanTinggi(): BelongsTo
    {
        return $this->belongsTo(PerguruanTinggi::class);
    }

    public function klaster(): BelongsTo
    {
        return $this->belongsTo(KlasterProdi::class, 'klaster_id');
    }

    public function snbpPerTahun(): HasMany
    {
        return $this->hasMany(SnbpProdiTahun::class);
    }

    public function hasilRasionalisasi(): HasMany
    {
        return $this->hasMany(HasilRasionalisasi::class);
    }
}
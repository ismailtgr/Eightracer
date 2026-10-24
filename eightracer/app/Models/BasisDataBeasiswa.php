<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BasisDataBeasiswa extends Model
{
    protected $table = 'basis_data_beasiswa';

    public $timestamps = false;

    protected $fillable = [
        'nama_beasiswa',
        'penyelenggara',
        'deskripsi',
        'jenjang',
        'tanggal_buka',
        'tanggal_tutup',
        'link_pendaftaran',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_buka' => 'date:Y-m-d',
            'tanggal_tutup' => 'date:Y-m-d',
            'is_active' => 'boolean',
        ];
    }

    public function alumniProgressBeasiswa(): HasMany
    {
        return $this->hasMany(AlumniProgressBeasiswa::class, 'beasiswa_id');
    }

    public function kriteriaKelayakanBeasiswa(): HasMany
    {
        return $this->hasMany(KriteriaKelayakanBeasiswa::class, 'beasiswa_id');
    }

    public function rekomendasiBeasiswaDetail(): HasMany
    {
        return $this->hasMany(RekomendasiBeasiswaDetail::class, 'beasiswa_id');
    }
}

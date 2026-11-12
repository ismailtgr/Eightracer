<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Services\Beasiswa\ScholarshipSchedule;

class BasisDataBeasiswa extends Model
{
    protected $table = 'basis_data_beasiswa';

    public $timestamps = false;

    protected $appends = ['status_program', 'status_pendaftaran'];

    protected $attributes = ['is_active' => true];

    public function getStatusProgramAttribute(): string
    {
        return $this->is_active ? 'aktif' : 'diarsipkan';
    }

    public function getStatusPendaftaranAttribute(): string
    {
        return ScholarshipSchedule::registrationStatus($this);
    }

    protected $fillable = ['durasi', 'satuan_durasi', 'periode_apply', 'program_eligibilitas_id',
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
        return ['durasi' => 'decimal:2',
            'tanggal_buka' => 'date:Y-m-d',
            'tanggal_tutup' => 'date:Y-m-d',
            'is_active' => 'boolean', 'official_policy' => 'array',
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

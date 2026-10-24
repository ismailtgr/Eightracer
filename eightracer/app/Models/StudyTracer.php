<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudyTracer extends Model
{
    protected $table = 'study_tracer';

    public $timestamps = false;

    protected $fillable = [
        'siswa_id',
        'perguruan_tinggi_id',
        'buku_alumni_id',
        'strata_pendidikan',
        'program_studi',
        'jalur_masuk',
        'sub_jalur_mandiri',
        'pilihan_ke',
        'status_penerimaan',
        'nilai_rapor_pendaftaran',
        'skor_utbk',
        'kontak_wa_terbaru',
        'is_pelamar_kipk',
        'izinkan_tampil_di_buku',
        'tanggal_pengisian',
    ];

    protected function casts(): array
    {
        return [
            'nilai_rapor_pendaftaran' => 'float',
            'skor_utbk' => 'float',
            'is_pelamar_kipk' => 'boolean',
            'izinkan_tampil_di_buku' => 'boolean',
            'tanggal_pengisian' => 'date:Y-m-d',
        ];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function perguruanTinggi(): BelongsTo
    {
        return $this->belongsTo(PerguruanTinggi::class, 'perguruan_tinggi_id');
    }

    public function bukuAlumni(): BelongsTo
    {
        return $this->belongsTo(BukuAlumni::class, 'buku_alumni_id');
    }

    public function studyTracerPrestasi(): HasMany
    {
        return $this->hasMany(StudyTracerPrestasi::class, 'study_tracer_id');
    }

    public function rekomendasiProdi(): HasMany
    {
        return $this->hasMany(RekomendasiProdi::class, 'study_tracer_id');
    }

    public function alumniProgress(): HasMany
    {
        return $this->hasMany(AlumniProgress::class, 'study_tracer_id');
    }

    public function prestasi(): BelongsToMany
    {
        return $this->belongsToMany(PrestasiMurid::class, 'study_tracer_prestasi', 'study_tracer_id', 'prestasi_murid_id');
    }
}

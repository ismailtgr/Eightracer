<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Siswa extends Model
{
    protected $table = 'siswa';

    protected $fillable = [
        'user_id',
        'nisn',
        'nis',
        'nama_lengkap',
        'nama_panggilan',
        'jenis_kelamin',
        'tempat_lahir',
        'tanggal_lahir',
        'agama',
        'golongan_darah',
        'alamat_rumah',
        'tinggal_bersama',
        'anak_ke',
        'warna_favorit',
        'makanan_favorit',
        'bahasa_keseharian',
        'permainan_online',
        'permainan_offline',
        'nilai_tka',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date:Y-m-d',
            'nilai_tka' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function riwayatKelas(): HasMany
    {
        return $this->hasMany(RiwayatKelas::class, 'siswa_id');
    }

    public function mediaSosial(): HasMany
    {
        return $this->hasMany(MediaSosial::class, 'siswa_id');
    }

    public function saudaraTiri(): HasMany
    {
        return $this->hasMany(SaudaraTiri::class, 'siswa_id');
    }

    public function orangTua(): HasMany
    {
        return $this->hasMany(OrangTua::class, 'siswa_id');
    }

    public function wali(): HasOne
    {
        return $this->hasOne(Wali::class, 'siswa_id');
    }

    public function bantuanSiswa(): HasMany
    {
        return $this->hasMany(BantuanSiswa::class, 'siswa_id');
    }

    public function hobiSiswa(): HasMany
    {
        return $this->hasMany(HobiSiswa::class, 'siswa_id');
    }

    public function citaCitaSiswa(): HasMany
    {
        return $this->hasMany(CitaCitaSiswa::class, 'siswa_id');
    }

    public function rekomendasiMapel(): HasMany
    {
        return $this->hasMany(RekomendasiMapel::class, 'siswa_id');
    }

    public function pilihanMapelSiswa(): HasMany
    {
        return $this->hasMany(PilihanMapelSiswa::class, 'siswa_id');
    }

    public function nilaiRapor(): HasMany
    {
        return $this->hasMany(NilaiRapor::class, 'siswa_id');
    }

    public function rekapPresensi(): HasMany
    {
        return $this->hasMany(RekapPresensi::class, 'siswa_id');
    }

    public function catatanKarakter(): HasMany
    {
        return $this->hasMany(CatatanKarakter::class, 'siswa_id');
    }

    public function riwayatOrganisasi(): HasMany
    {
        return $this->hasMany(RiwayatOrganisasi::class, 'siswa_id');
    }

    public function prestasiMurid(): HasMany
    {
        return $this->hasMany(PrestasiMurid::class, 'siswa_id');
    }

    public function asesmenRiasec(): HasMany
    {
        return $this->hasMany(AsesmenRiasec::class, 'siswa_id');
    }

    public function asesmenMultipleIntelligence(): HasMany
    {
        return $this->hasMany(AsesmenMultipleIntelligence::class, 'siswa_id');
    }

    public function asesmenKarakteristik(): HasMany
    {
        return $this->hasMany(AsesmenKarakteristik::class, 'siswa_id');
    }

    public function hasilEligibilitas(): HasMany
    {
        return $this->hasMany(HasilEligibilitas::class, 'siswa_id');
    }

    public function studyTracer(): HasMany
    {
        return $this->hasMany(StudyTracer::class, 'siswa_id');
    }

    public function rekomendasiProdi(): HasMany
    {
        return $this->hasMany(RekomendasiProdi::class, 'siswa_id');
    }

    public function alumniProgress(): HasMany
    {
        return $this->hasMany(AlumniProgress::class, 'siswa_id');
    }

    public function rekomendasiBeasiswa(): HasMany
    {
        return $this->hasMany(RekomendasiBeasiswa::class, 'siswa_id');
    }

    public function hobi(): BelongsToMany
    {
        return $this->belongsToMany(Hobi::class, 'hobi_siswa', 'siswa_id', 'hobi_id');
    }

    public function citaCita(): BelongsToMany
    {
        return $this->belongsToMany(CitaCita::class, 'cita_cita_siswa', 'siswa_id', 'cita_cita_id')->withPivot('prioritas');
    }
}

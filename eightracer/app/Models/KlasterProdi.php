<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KlasterProdi extends Model
{
    protected $table = 'klaster_prodi';

    public $timestamps = false;

    protected $fillable = [
        'nama_klaster',
        'kode_riasec_relevan',
        'kecerdasan_relevan',
        'deskripsi',
    ];

    public function bobotMapelKlaster(): HasMany
    {
        return $this->hasMany(BobotMapelKlaster::class, 'klaster_id');
    }

    public function rekomendasiProdi(): HasMany
    {
        return $this->hasMany(RekomendasiProdi::class, 'klaster_id');
    }

    public function mataPelajaran(): BelongsToMany
    {
        return $this->belongsToMany(MataPelajaran::class, 'bobot_mapel_klaster', 'klaster_id', 'mata_pelajaran_id')->withPivot('bobot_persentase');
    }
}

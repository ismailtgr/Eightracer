<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CitaCita extends Model
{
    protected $table = 'cita_cita';

    public $timestamps = false;

    protected $fillable = [
        'nama_cita_cita',
    ];

    public function citaCitaSiswa(): HasMany
    {
        return $this->hasMany(CitaCitaSiswa::class, 'cita_cita_id');
    }

    public function siswa(): BelongsToMany
    {
        return $this->belongsToMany(Siswa::class, 'cita_cita_siswa', 'cita_cita_id', 'siswa_id')->withPivot('prioritas');
    }
}

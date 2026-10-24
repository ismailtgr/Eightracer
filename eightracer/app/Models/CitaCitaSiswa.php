<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CitaCitaSiswa extends Model
{
    protected $table = 'cita_cita_siswa';

    public $timestamps = false;

    protected $fillable = [
        'siswa_id',
        'cita_cita_id',
        'prioritas',
    ];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function citaCita(): BelongsTo
    {
        return $this->belongsTo(CitaCita::class, 'cita_cita_id');
    }
}

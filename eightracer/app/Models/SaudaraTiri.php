<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaudaraTiri extends Model
{
    protected $table = 'saudara_tiri';

    public $timestamps = false;

    protected $fillable = [
        'siswa_id',
        'nama',
        'jenis_kelamin',
        'hubungan',
        'usia',
        'keterangan',
    ];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }
}

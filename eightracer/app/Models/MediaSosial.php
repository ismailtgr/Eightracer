<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaSosial extends Model
{
    protected $table = 'media_sosial';

    public $timestamps = false;

    protected $fillable = [
        'siswa_id',
        'jenis_media',
        'username',
        'nomor_wa',
    ];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }
}

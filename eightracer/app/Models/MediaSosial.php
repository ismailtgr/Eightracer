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
        'jenis_media', 'platform',
        'username',
        'nomor_wa',
    ];

    protected $appends = ['platform'];

    public function getPlatformAttribute(): ?string
    {
        return $this->attributes['jenis_media'] ?? null;
    }

    public function setPlatformAttribute(string $value): void
    {
        $this->attributes['jenis_media'] = $value;
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }
}

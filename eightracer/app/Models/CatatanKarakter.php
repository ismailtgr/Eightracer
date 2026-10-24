<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatatanKarakter extends Model
{
    protected $table = 'catatan_karakter';

    public $timestamps = false;

    protected $fillable = [
        'siswa_id',
        'semester',
        'jenis_catatan',
        'poin_disiplin',
        'deskripsi',
        'tanggal_pencatatan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_pencatatan' => 'datetime',
        ];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }
}

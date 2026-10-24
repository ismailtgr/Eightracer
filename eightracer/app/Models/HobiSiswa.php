<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HobiSiswa extends Model
{
    protected $table = 'hobi_siswa';

    public $timestamps = false;

    protected $fillable = [
        'siswa_id',
        'hobi_id',
    ];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function hobi(): BelongsTo
    {
        return $this->belongsTo(Hobi::class, 'hobi_id');
    }
}

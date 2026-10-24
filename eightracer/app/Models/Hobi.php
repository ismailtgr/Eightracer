<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hobi extends Model
{
    protected $table = 'hobi';

    public $timestamps = false;

    protected $fillable = [
        'nama_hobi',
    ];

    public function hobiSiswa(): HasMany
    {
        return $this->hasMany(HobiSiswa::class, 'hobi_id');
    }

    public function siswa(): BelongsToMany
    {
        return $this->belongsToMany(Siswa::class, 'hobi_siswa', 'hobi_id', 'siswa_id');
    }
}

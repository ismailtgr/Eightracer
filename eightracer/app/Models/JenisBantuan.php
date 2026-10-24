<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisBantuan extends Model
{
    protected $table = 'jenis_bantuan';

    public $timestamps = false;

    protected $fillable = [
        'nama_bantuan',
        'keterangan',
    ];

    public function bantuanSiswa(): HasMany
    {
        return $this->hasMany(BantuanSiswa::class, 'jenis_bantuan_id');
    }
}

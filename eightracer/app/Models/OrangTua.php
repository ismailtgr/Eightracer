<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrangTua extends Model
{
    protected $table = 'orang_tua';

    public $timestamps = false;

    protected $fillable = [
        'siswa_id',
        'jenis_orang_tua',
        'nama',
        'kondisi',
        'pekerjaan',
        'penghasilan',
        'alamat',
        'kontak_wa',
    ];

    protected function casts(): array
    {
        return [
            'penghasilan' => 'decimal:2',
        ];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }
}

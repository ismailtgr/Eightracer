<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wali extends Model
{
    protected $table = 'wali';

    public $timestamps = false;

    protected $fillable = [
        'siswa_id',
        'nama',
        'hubungan',
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatOrganisasi extends Model
{
    protected $table = 'riwayat_organisasi';

    public $timestamps = false;

    protected $fillable = [
        'siswa_id',
        'jenis_organisasi',
        'nama_organisasi',
        'jabatan',
        'bobot_jabatan',
        'periode_jabatan',
    ];

    protected function casts(): array
    {
        return [
            'bobot_jabatan' => 'decimal:2',
        ];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }
}

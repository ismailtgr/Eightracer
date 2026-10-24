<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NilaiRapor extends Model
{
    protected $table = 'nilai_rapor';

    public $timestamps = false;

    protected $fillable = [
        'siswa_id',
        'mata_pelajaran_id',
        'semester',
        'nilai_akhir',
    ];

    protected function casts(): array
    {
        return [
            'nilai_akhir' => 'decimal:2',
        ];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class, 'mata_pelajaran_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsesmenRiasec extends Model
{
    protected $table = 'asesmen_riasec';

    public $timestamps = false;

    protected $fillable = [
        'siswa_id',
        'realistic_score',
        'investigative_score',
        'artistic_score',
        'social_score',
        'enterprising_score',
        'conventional_score',
        'kode_hasil',
        'tanggal_asesmen',
    ];

    protected function casts(): array
    {
        return [
            'realistic_score' => 'decimal:2',
            'investigative_score' => 'decimal:2',
            'artistic_score' => 'decimal:2',
            'social_score' => 'decimal:2',
            'enterprising_score' => 'decimal:2',
            'conventional_score' => 'decimal:2',
            'tanggal_asesmen' => 'date:Y-m-d',
        ];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }
}

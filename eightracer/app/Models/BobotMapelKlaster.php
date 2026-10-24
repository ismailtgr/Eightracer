<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BobotMapelKlaster extends Model
{
    protected $table = 'bobot_mapel_klaster';

    public $timestamps = false;

    protected $fillable = [
        'klaster_id',
        'mata_pelajaran_id',
        'bobot_persentase',
    ];

    protected function casts(): array
    {
        return [
            'bobot_persentase' => 'decimal:2',
        ];
    }

    public function klaster(): BelongsTo
    {
        return $this->belongsTo(KlasterProdi::class, 'klaster_id');
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class, 'mata_pelajaran_id');
    }
}

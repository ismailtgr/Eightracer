<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerguruanTinggi extends Model
{
    protected $table = 'perguruan_tinggi';

    public $timestamps = false;

    protected $fillable = [
        'nama_pt',
        'jenis_pt',
        'kode_pt',
        'lokasi',
    ];

    protected function casts(): array
    {
        return [
            'lokasi' => 'array',
        ];
    }

    public function studyTracer(): HasMany
    {
        return $this->hasMany(StudyTracer::class, 'perguruan_tinggi_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeriodeEligibilitas extends Model
{
    protected $table = 'periode_eligibilitas';

    public $timestamps = false;

    protected $fillable = [
        'tahun_ajaran',
        'kuota_siswa',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function hasilEligibilitas(): HasMany
    {
        return $this->hasMany(HasilEligibilitas::class, 'periode_id');
    }
}

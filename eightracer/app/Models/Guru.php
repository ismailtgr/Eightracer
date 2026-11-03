<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Guru extends Model
{
    protected $table = 'guru';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'nip',
        'nama_lengkap',
        'jabatan',
        'foto_profil',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getInisialAttribute(): string
    {
        $tanpaGelarBelakang = explode(',', (string) $this->nama_lengkap)[0];
        $bersih = preg_replace('/^(Dra\.|Drs\.|Dr\.|Prof\.|Ir\.|H\.|Hj\.)\s+/i', '', trim($tanpaGelarBelakang));
        $kata   = preg_split('/\s+/', trim((string) $bersih)) ?: [];

        $huruf1 = substr($kata[0] ?? 'G', 0, 1);
        $huruf2 = isset($kata[1]) ? substr($kata[1], 0, 1) : substr($kata[0] ?? 'B', 1, 1);

        return strtoupper($huruf1 . $huruf2);
    }

    public function getFotoProfilUrlAttribute(): ?string
    {
        if (!$this->foto_profil) {
            return null;
        }

        return asset('storage/' . ltrim($this->foto_profil, '/'));
    }
}

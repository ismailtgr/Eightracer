<?php

namespace App\Services\Beasiswa;

use App\Models\Siswa;

class ScholarshipInterestProfile
{
    public function document(Siswa $siswa): string
    {
        $siswa->loadMissing(['hobi', 'citaCita', 'prestasiMurid']);
        // Only explicitly recorded interests and verified fields; never raw source notes or identities.
        $terms = [...$siswa->hobi->sortBy('id')->pluck('nama_hobi')->all(),
            ...$siswa->citaCita->sortBy('id')->pluck('nama_cita_cita')->all(),
            ...$siswa->prestasiMurid->where('status_verifikasi', 'terverifikasi')->sortBy('id')->pluck('bidang')->all()];
        $terms = array_filter(array_map(fn ($v) => trim((string) $v), $terms));
        sort($terms, SORT_STRING);

        return mb_substr(implode(' ', array_unique($terms)), 0, 12000);
    }
}

<?php

namespace App\Services\Beasiswa;

use App\Models\BasisDataBeasiswa;

class ScholarshipSchedule
{
    public const TIMEZONE = 'Asia/Jakarta';

    public static function today(): string
    {
        return now(self::TIMEZONE)->toDateString();
    }

    public static function registrationStatus(BasisDataBeasiswa $scholarship): string
    {
        if (! $scholarship->is_active) {
            return 'tidak_tersedia';
        }
        // SQL DATE values describe calendar days; do not shift them between timezones.
        $open = $scholarship->tanggal_buka?->format('Y-m-d');
        $close = $scholarship->tanggal_tutup?->format('Y-m-d');
        $today = self::today();
        if ($open && $close && $close < $open) {
            return 'belum_ditentukan';
        }
        if ($close && $today > $close) {
            return 'ditutup';
        }
        if ($open && $today < $open) {
            return 'belum_dibuka';
        }
        if ($open && $close) {
            return 'dibuka';
        }

        return 'belum_ditentukan';
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Proteksi duplikat katalog di level database.
 *
 * Audit read-only pada 2026-11-16 (5 baris, 0 duplikat) menjadi dasar migrasi ini.
 * Jika ternyata masih ada nama+jenjang ganda, migrasi berhenti tanpa mengubah data
 * agar operator dapat membersihkan duplikat terlebih dahulu.
 *
 * Catatan: CREATE INDEX membutuhkan ownership tabel. Jalankan dengan role migrator
 * (pemilik tabel), bukan role runtime aplikasi.
 */
return new class extends Migration
{
    private const INDEX = 'basis_data_beasiswa_nama_jenjang_unique';

    public function up(): void
    {
        $duplicates = DB::select(
            "SELECT LOWER(TRIM(nama_beasiswa)) AS nama, COALESCE(jenjang, '') AS jenjang, COUNT(*) AS total
             FROM basis_data_beasiswa
             GROUP BY LOWER(TRIM(nama_beasiswa)), COALESCE(jenjang, '')
             HAVING COUNT(*) > 1"
        );
        if ($duplicates !== []) {
            $sample = implode(', ', array_map(
                fn ($row) => sprintf('"%s" (%s)', $row->nama, $row->jenjang ?: 'tanpa jenjang'),
                array_slice($duplicates, 0, 3)
            ));
            throw new RuntimeException(
                'Unique index belum dapat dibuat: ada '.count($duplicates).' nama+jenjang ganda, mis. '.$sample.'. Bersihkan duplikat terlebih dahulu.'
            );
        }

        DB::statement('CREATE UNIQUE INDEX '.self::INDEX.' ON basis_data_beasiswa (LOWER(TRIM(nama_beasiswa)), COALESCE(jenjang, \'\'))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS '.self::INDEX);
    }
};

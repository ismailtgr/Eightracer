<?php

namespace Database\Seeders;

use App\Models\PerguruanTinggi;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PerguruanTinggiSeeder extends Seeder
{
    public function run(): void
    {
        $csvPath = storage_path('app/datasets/LIST UNIVERSITAS.csv');

        if (! file_exists($csvPath)) {
            $this->command->error("File CSV tidak ditemukan: {$csvPath}");
            return;
        }

        $handle = fopen($csvPath, 'r');
        if (! $handle) {
            $this->command->error("Gagal membuka file CSV: {$csvPath}");
            return;
        }

        // Baca header
        $header = fgetcsv($handle);
        if (! $header) {
            $this->command->error('CSV kosong atau header tidak terbaca.');
            fclose($handle);
            return;
        }

        // Normalisasi nama kolom: trim, lowercase, ganti spasi dengan _
        $header = array_map(function ($col) {
            return Str::slug(trim((string) $col), '_');
        }, $header);

        $this->command->info('Header: ' . implode(', ', $header));

        $total = 0;
        $created = 0;
        $updated = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $total++;

            if (count($row) !== count($header)) {
                // Baris tidak sesuai jumlah kolom, lewati
                $skipped++;
                continue;
            }

            $data = array_combine($header, $row);

            $nama = trim((string) ($data['nama'] ?? ''));
            if ($nama === '') {
                $skipped++;
                continue;
            }

            // Normalisasi nama PT: uppercase, trim, hapus spasi ganda
            $namaNormalized = Str::upper(preg_replace('/\s+/', ' ', $nama));

            $npsn = trim((string) ($data['npsn'] ?? ''));
            $status = Str::lower(trim((string) ($data['status'] ?? '')));
            $jenisPt = null;
            if ($status === 'negeri') {
                $jenisPt = 'Negeri';
            } elseif ($status === 'swasta') {
                $jenisPt = 'Swasta';
            }

            $existing = PerguruanTinggi::whereRaw('UPPER(TRIM(nama_pt)) = ?', [$namaNormalized])->first();

            $payload = [
                'nama_pt'       => $namaNormalized,
                'nama_singkat'  => $this->nullIfEmpty($data['nama_singkat'] ?? null),
                'kode_pt'       => $this->nullIfEmpty($npsn),
                'jenis_pt'      => $jenisPt,
                'kota'          => $this->nullIfEmpty($data['kota'] ?? null),
                'provinsi'      => $this->nullIfEmpty($data['provinsi'] ?? null),
                'akreditasi'    => $this->nullIfEmpty($data['akreditasi'] ?? null),
                'telepon'       => $this->nullIfEmpty($data['telepon'] ?? null),
                'email'         => $this->nullIfEmpty($data['email'] ?? null),
                'alamat'        => $this->nullIfEmpty($data['alamat'] ?? null),
                'kode_pos'      => $this->nullIfEmpty($data['kode_pos'] ?? null),
            ];

            if ($existing) {
                $existing->update($payload);
                $updated++;
            } else {
                PerguruanTinggi::create($payload);
                $created++;
            }
        }

        fclose($handle);

        $this->command->info("Selesai. Total baris: {$total}, dibuat: {$created}, diupdate: {$updated}, dilewati: {$skipped}");
    }

    private function nullIfEmpty(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);
        if ($trimmed === '' || $trimmed === 'NULL' || $trimmed === 'null') {
            return null;
        }
        return $trimmed;
    }
}
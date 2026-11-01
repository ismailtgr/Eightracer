<?php

namespace App\Imports;

use App\Models\Siswa;
use App\Models\MataPelajaran;
use App\Models\NilaiRapor;
use App\Services\RekomendasiMapelService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class NilaiRaporImport implements ToCollection, WithHeadingRow, WithMultipleSheets
{
    public array $errors        = [];
    public int   $successCount  = 0;
    public int   $skippedCount  = 0;

    public function sheets(): array
    {
        return [0 => $this];
    }

    private const KODE_MAPEL = [
        'mat'  => 'matematika',
        'ind'  => 'bahasa indonesia',
        'ing'  => 'bahasa inggris',
        'fis'  => 'fisika',
        'kim'  => 'kimia',
        'bio'  => 'biologi',
        'eko'  => 'ekonomi',
        'geo'  => 'geografi',
        'sos'  => 'sosiologi',
        'sej'  => 'sejarah',
        'inf'  => 'informatika',
        'sbd'  => 'seni budaya',
        'pjok' => 'pendidikan jasmani',
        'pkn'  => 'pendidikan pancasila',
        'pai'  => 'pendidikan agama',
    ];

    public function collection(Collection $rows): void
    {
        $semuaMapel = MataPelajaran::all()->keyBy(fn ($m) => strtolower($m->nama_mapel));
        $rekomendasiService = new RekomendasiMapelService();
        $tahunAjaran = date('Y') . '/' . ((int) date('Y') + 1);

        foreach ($rows as $index => $row) {
            $nisn = trim((string) ($row['nisn'] ?? $row['nis'] ?? ''));
            $nama = trim((string) ($row['nama_lengkap'] ?? $row['nama'] ?? ''));
            if ($nisn === '' && $nama === '') {
                continue;
            }

            $semester = (int) ($row['semester'] ?? 1);
            if ($semester < 1) {
                $semester = 1;
            }

            try {
                $imported = DB::transaction(function () use ($row, $nisn, $nama, $semester, $semuaMapel, $rekomendasiService, $tahunAjaran) {
                    $siswa = null;
                    if ($nisn !== '') {
                        $siswa = Siswa::where('nisn', $nisn)->orWhere('nis', $nisn)->first();
                    }
                    if (!$siswa && $nama !== '') {
                        $siswa = Siswa::whereRaw('LOWER(TRIM(nama_lengkap)) = ?', [strtolower($nama)])->first();
                    }

                    if (!$siswa) {
                        throw new \RuntimeException("Siswa dengan NISN/NIS '{$nisn}' tidak ditemukan di sistem.");
                    }

                    $jumlahDisimpan = 0;

                    foreach (self::KODE_MAPEL as $kode => $substring) {
                        $kolomExcel = 'nilai_' . $kode;
                        $nilaiRaw   = $row[$kolomExcel] ?? null;

                        if ($nilaiRaw === null || trim((string) $nilaiRaw) === '') {
                            continue;
                        }

                        $nilai = $this->toFloat($nilaiRaw);
                        if ($nilai === null) {
                            continue;
                        }

                        $mapel = $semuaMapel->get($substring)
                            ?? $semuaMapel->first(
                                fn ($m) => str_contains(strtolower($m->nama_mapel), $substring)
                            );

                        if (!$mapel) {
                            continue;
                        }

                        NilaiRapor::updateOrCreate(
                            [
                                'siswa_id'          => $siswa->id,
                                'mata_pelajaran_id' => $mapel->id,
                                'semester'          => $semester,
                            ],
                            ['nilai_akhir' => $nilai]
                        );

                        $jumlahDisimpan++;
                    }

                    if ($jumlahDisimpan === 0) {
                        throw new \RuntimeException(
                            'Tidak ada nilai yang tersimpan: kolom nilai_* kosong/bukan angka, '
                            . 'atau mata pelajaran belum ada di tabel mata_pelajaran (cek nama mapel).'
                        );
                    }

                    if ($semester === 1) {
                        $rekomendasiService->generate($siswa, $tahunAjaran);
                    }

                    return $jumlahDisimpan;
                });

                $this->successCount++;

            } catch (\Throwable $e) {
                $this->errors[] = [
                    'baris' => $index + 2,
                    'nisn'  => $nisn,
                    'pesan' => $e->getMessage(),
                ];
            }
        }
    }

    private function toFloat($nilai): ?float
    {
        $v = trim((string) ($nilai ?? ''));
        return $v === '' || !is_numeric($v) ? null : (float) $v;
    }
}

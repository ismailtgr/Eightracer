<?php

namespace App\Imports;

use App\Models\AsesmenKarakteristik;
use App\Models\AsesmenMultipleIntelligence;
use App\Models\AsesmenRiasec;
use App\Models\MinatProdiSiswa;
use App\Models\Siswa;
use App\Services\RekomendasiMapelService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class AsesmenImport implements ToCollection, WithMultipleSheets
{
    public array $errors       = [];
    public int   $successCount = 0;
    public int   $skippedCount = 0;
    public array $peringatan   = [];

    public function sheets(): array
    {
        return [0 => $this];
    }

    private const ALIAS = [
        'nis'     => ['nis'],
        'nisn'    => ['nisn'],
        'nama'    => ['nama lengkap', 'nama'],
        'jk'      => ['jenis kelamin', 'jk', 'l p', 'gender'],
        'agama'   => ['agm', 'agama'],
        'mi'      => ['multiple intelegence', 'multiple intelligence', 'kecerdasan majemuk', 'mi'],
        'riasec'  => ['riasec'],
        'prospek' => ['prospek profil diri', 'prospek', 'minat prodi', 'prodi pilihan 1'],
        'prodi_2' => ['prodi pilihan 2', 'prodi 2'],
    ];

    private const MI_KATA = [
        'linguistik'      => ['lingu', 'bahasa', 'verbal'],
        'logis_matematis' => ['logis', 'matemat', 'angka', 'numerik'],
        'spasial'         => ['spasial', 'visual', 'ruang'],
        'musikal'         => ['musik', 'irama'],
        'kinestetik'      => ['kinest', 'badan', 'jasmani', 'gerak', 'fisik'],
        'interpersonal'   => ['interpers', 'sosial'],
        'intrapersonal'   => ['intrapers', 'diri'],
        'naturalis'       => ['natural', 'alam', 'lingkungan'],
    ];

    public function collection(Collection $rows): void
    {
        $rows = $rows->values();

        [$headerIdx, $map] = $this->bacaHeader($rows);

        if ($headerIdx === null) {
            $this->errors[] = [
                'baris' => 1,
                'nisn'  => '-',
                'pesan' => 'Header tidak ditemukan. Wajib ada kolom NIS (atau NISN) dan NAMA LENGKAP.',
            ];
            return;
        }

        foreach ($rows as $i => $row) {
            if ($i <= $headerIdx) {
                continue;
            }

            $baris = $i + 1;
            $nis   = $this->cell($row, $map['nis'] ?? null);
            $nisn  = $this->cell($row, $map['nisn'] ?? null);
            $nama  = $this->cell($row, $map['nama'] ?? null);

            if ($nis === '' && $nisn === '' && $nama === '') {
                continue;
            }

            $label = $nis !== '' ? $nis : ($nisn !== '' ? $nisn : $nama);

            try {
                $result = DB::transaction(function () use ($row, $map, $nis, $nisn, $nama, $baris, $label) {
                    $siswa = $this->cariSiswa($nis, $nisn, $nama);

                    // 0. Lengkapi NIS, Jenis Kelamin, dan Agama di tabel siswa dari file asesmen
                    $adaBiodata = $this->lengkapiBiodataSiswa($siswa, $row, $map, $nis);

                    $karakteristik = [];

                    // 1. Multiple Intelligence
                    $miList = $this->parseMi($this->cell($row, $map['mi'] ?? null), $baris, $label);
                    if (!empty($miList)) {
                        $karakteristik['kecerdasan_majemuk_dominan'] = implode(',', $miList);
                        $this->simpanSkorMi($siswa, $miList);
                    }

                    // 2. RIASEC (1 atau 3 kolom)
                    $riasec = $this->kodeRiasec($row, $map['riasec'] ?? [], $baris, $label);
                    if ($riasec !== null) {
                        $karakteristik['kode_riasec'] = $riasec;
                        $this->simpanSkorRiasec($siswa, $riasec);
                    }

                    // 3. PROSPEK (PROFIL DIRI) sebagai Minat Prodi
                    $adaProdi = $this->simpanMinatProdi($siswa, $row, $map);

                    if (empty($karakteristik) && !$adaProdi && !$adaBiodata) {
                        return 'skipped';
                    }

                    if (!empty($karakteristik)) {
                        $karakteristik['tanggal_asesmen'] = now();
                        AsesmenKarakteristik::updateOrCreate(['siswa_id' => $siswa->id], $karakteristik);
                    }

                    // Otomatis hitung/perbarui draf rekomendasi mapel pilihan
                    $tahunAjaran = date('Y') . '/' . ((int) date('Y') + 1);
                    (new RekomendasiMapelService())->generate($siswa, $tahunAjaran);

                    return true;
                });

                $result === true ? $this->successCount++ : $this->skippedCount++;
            } catch (\Throwable $e) {
                $this->errors[] = [
                    'baris' => $baris,
                    'nisn'  => $label,
                    'pesan' => $e->getMessage(),
                ];
            }
        }
    }

    private function bacaHeader(Collection $rows): array
    {
        foreach ($rows->take(10) as $i => $row) {
            $cells = array_values($row instanceof Collection ? $row->all() : (array) $row);
            $norm  = array_map(fn ($c) => $this->norm($c), $cells);

            if (!in_array('nis', $norm, true) && !in_array('nisn', $norm, true) && !in_array('nama lengkap', $norm, true)) {
                continue;
            }

            $grup = [];
            $saat = null;
            foreach ($norm as $col => $label) {
                if ($label !== '') {
                    $saat = $label;
                    $grup[$saat] = [$col];
                } elseif ($saat !== null) {
                    $grup[$saat][] = $col;
                }
            }

            $map = [];
            foreach (self::ALIAS as $kunci => $alias) {
                foreach ($alias as $a) {
                    if (isset($grup[$a])) {
                        $map[$kunci] = $kunci === 'riasec' ? $grup[$a] : $grup[$a][0];
                        break;
                    }
                }
            }

            // Jika kolom Jenis Kelamin tidak punya judul (berada di sebelah kanan NAMA LENGKAP sebelum AGM)
            if (!isset($map['jk'])) {
                foreach (['nama lengkap', 'nama'] as $namaKey) {
                    if (isset($grup[$namaKey]) && count($grup[$namaKey]) > 1) {
                        $map['jk'] = $grup[$namaKey][1];
                        break;
                    }
                }
            }

            return [$i, $map];
        }

        return [null, []];
    }

    private function norm($v): string
    {
        $v = strtolower(trim((string) ($v ?? '')));
        $v = preg_replace('/[^a-z0-9]+/', ' ', $v);

        return trim($v);
    }

    private function cell($row, $col): string
    {
        if ($col === null) {
            return '';
        }

        $cells = array_values($row instanceof Collection ? $row->all() : (array) $row);
        $v     = $cells[$col] ?? null;

        if (is_float($v) && floor($v) == $v) {
            $v = (int) $v;
        }

        return trim((string) ($v ?? ''));
    }

    private function cariSiswa(string $nis, string $nisn, string $nama): Siswa
    {
        if ($nis !== '' && $siswa = Siswa::where('nis', $nis)->first()) {
            return $siswa;
        }

        if ($nis !== '' && $siswa = Siswa::where('nisn', $nis)->first()) {
            return $siswa;
        }

        if ($nisn !== '' && $siswa = Siswa::where('nisn', $nisn)->first()) {
            return $siswa;
        }

        if ($nama !== '') {
            $cocok = Siswa::whereRaw('LOWER(TRIM(nama_lengkap)) = ?', [strtolower($nama)])->get();

            if ($cocok->count() === 1) {
                $siswa = $cocok->first();
                if ($nis !== '' && empty($siswa->nis)) {
                    $siswa->update(['nis' => $nis]);
                }
                return $siswa;
            }
            if ($cocok->count() > 1) {
                throw new \RuntimeException("Nama '{$nama}' dipakai {$cocok->count()} siswa, pastikan NIS/NISN sesuai.");
            }
        }

        throw new \RuntimeException("Siswa ('{$nama}' / NIS '{$nis}') tidak ditemukan di database. Import biodata siswa dulu.");
    }

    /**
     * Lengkapi kolom nis, jenis_kelamin, dan agama pada tabel siswa dari file asesmen.
     */
    private function lengkapiBiodataSiswa(Siswa $siswa, $row, array $map, string $nis): bool
    {
        $update = [];

        if ($nis !== '' && $siswa->nis !== $nis) {
            $update['nis'] = $nis;
        }

        $jkRaw = strtoupper($this->cell($row, $map['jk'] ?? null));
        if ($jkRaw !== '') {
            if (in_array($jkRaw, ['L', 'LAKI-LAKI', 'LAKI LAKI', 'PRIA', 'LK'], true) || str_starts_with($jkRaw, 'L')) {
                $update['jenis_kelamin'] = 'L';
            } elseif (in_array($jkRaw, ['P', 'PEREMPUAN', 'WANITA', 'PR'], true) || str_starts_with($jkRaw, 'P')) {
                $update['jenis_kelamin'] = 'P';
            }
        }

        $agamaRaw = $this->cell($row, $map['agama'] ?? null);
        if ($agamaRaw !== '') {
            $kodeAgama = strtoupper($agamaRaw);
            $petaAgama = [
                'ISL'       => 'Islam',
                'ISLAM'     => 'Islam',
                'KRIS'      => 'Kristen',
                'KRISTEN'   => 'Kristen',
                'PROTESTAN' => 'Kristen',
                'KP'        => 'Kristen',
                'KAT'       => 'Katolik',
                'KATH'      => 'Katolik',
                'KATOLIK'   => 'Katolik',
                'KATHOLIK'  => 'Katolik',
                'HIN'       => 'Hindu',
                'HINDU'     => 'Hindu',
                'BUD'       => 'Buddha',
                'BUDHA'     => 'Buddha',
                'BUDDHA'    => 'Buddha',
                'KONG'      => 'Konghucu',
                'KONGHUCU'  => 'Konghucu',
            ];
            $update['agama'] = $petaAgama[$kodeAgama] ?? ucfirst(strtolower($agamaRaw));
        }

        if (!empty($update)) {
            $siswa->update($update);
            return true;
        }

        return false;
    }

    private function parseMi(string $teks, int $baris, string $id): array
    {
        if ($teks === '') {
            return [];
        }

        $hasil = [];
        foreach (preg_split('/[,;\/&+]|\bdan\b/i', $teks) as $bagian) {
            $bagian = strtolower(trim($bagian));
            if ($bagian === '') {
                continue;
            }

            $ketemu = null;
            foreach (self::MI_KATA as $dimensi => $kata) {
                foreach ($kata as $k) {
                    if (str_contains($bagian, $k)) {
                        $ketemu = $dimensi;
                        break 2;
                    }
                }
            }

            if ($ketemu === null) {
                $this->peringatan[] = [
                    'baris' => $baris,
                    'nisn'  => $id,
                    'pesan' => "Multiple Intelligence '{$bagian}' tidak dikenali, disimpan apa adanya",
                ];
                $ketemu = $bagian;
            }

            $hasil[$ketemu] = true;
        }

        return array_keys($hasil);
    }

    private function simpanSkorMi(Siswa $siswa, array $miDominan): void
    {
        $skorUrutan = [85.0, 78.0, 72.0];
        foreach ($miDominan as $idx => $jenis) {
            $skor = $skorUrutan[$idx] ?? 70.0;
            AsesmenMultipleIntelligence::updateOrCreate(
                ['siswa_id' => $siswa->id, 'jenis_kecerdasan' => $jenis],
                ['skor' => $skor, 'tanggal_asesmen' => now()->format('Y-m-d')]
            );
        }
    }

    private function kodeRiasec($row, array $kolom, int $baris, string $id): ?string
    {
        $gabung = '';
        foreach ($kolom as $c) {
            $gabung .= $this->cell($row, $c);
        }

        if (trim($gabung) === '') {
            return null;
        }

        $huruf = preg_replace('/[^RIASEC]/', '', strtoupper($gabung));
        $huruf = implode('', array_unique(str_split($huruf)));

        if ($huruf === '') {
            $this->peringatan[] = [
                'baris' => $baris,
                'nisn'  => $id,
                'pesan' => "Kode RIASEC '{$gabung}' tidak berisi huruf R/I/A/S/E/C, dilewati",
            ];
            return null;
        }

        return substr($huruf, 0, 3);
    }

    private function simpanSkorRiasec(Siswa $siswa, string $kode): void
    {
        $skor = ['R' => 50.0, 'I' => 50.0, 'A' => 50.0, 'S' => 50.0, 'E' => 50.0, 'C' => 50.0];
        $bobotPeringkat = [85.0, 75.0, 65.0];

        foreach (str_split($kode) as $idx => $huruf) {
            if (isset($skor[$huruf]) && isset($bobotPeringkat[$idx])) {
                $skor[$huruf] = $bobotPeringkat[$idx];
            }
        }

        AsesmenRiasec::updateOrCreate(
            ['siswa_id' => $siswa->id],
            [
                'realistic_score'     => $skor['R'],
                'investigative_score' => $skor['I'],
                'artistic_score'      => $skor['A'],
                'social_score'        => $skor['S'],
                'enterprising_score'  => $skor['E'],
                'conventional_score'  => $skor['C'],
                'kode_hasil'          => $kode,
                'tanggal_asesmen'     => now()->format('Y-m-d'),
            ]
        );
    }

    private function simpanMinatProdi(Siswa $siswa, $row, array $map): bool
    {
        $prospek = $this->cell($row, $map['prospek'] ?? null);
        $prodi2  = $this->cell($row, $map['prodi_2'] ?? null);

        if ($prospek === '' && $prodi2 === '') {
            return false;
        }

        $daftar = [];
        if ($prospek !== '') {
            foreach (preg_split('/[\/;,\n]+/', $prospek) as $item) {
                $item = trim($item);
                if ($item !== '') {
                    $daftar[] = $item;
                }
            }
        }
        if ($prodi2 !== '' && count($daftar) < 2) {
            $daftar[] = $prodi2;
        }

        $ada = false;
        foreach (array_slice($daftar, 0, 2) as $idx => $teks) {
            $prioritas = $idx + 1;
            $namaProdi = $teks;
            $pt        = null;

            if (preg_match('/^(.+?)\s*\(([^)]+)\)\s*$/', $teks, $m)) {
                $namaProdi = trim($m[1]);
                $pt        = trim($m[2]);
            } elseif (preg_match('/^(.+?)\s+-\s+(.+)$/', $teks, $m)) {
                $namaProdi = trim($m[1]);
                $pt        = trim($m[2]);
            }

            MinatProdiSiswa::updateOrCreate(
                ['siswa_id' => $siswa->id, 'prioritas' => $prioritas],
                ['nama_prodi' => $namaProdi, 'perguruan_tinggi' => $pt]
            );
            $ada = true;
        }

        return $ada;
    }
}

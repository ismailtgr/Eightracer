<?php

namespace App\Imports;

use App\Models\PerguruanTinggi;
use App\Models\Siswa;
use App\Models\StudyTracer;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class AlumniSheetImport implements ToCollection, WithHeadingRow
{
    private const UNIV_KEYWORDS = [
        'UNIVERSITAS',
        'UNIV',
        'INSTITUT',
        'POLITEKNIK',
        'POLITEK',
        'SEKOLAH TINGGI',
        'STIE',
        'STIKES',
        'STMIK',
        'STAI',
        'UIN',
        'IAIN',
        'UPN',
        'UGM',
        'UI',
        'ITB',
        'ITS',
        'UNESA',
        'UM',
        'UB',
        'UNAIR',
        'UNDIP',
        'UNS',
        'UNY',
        'UNM',
        'UNJ',
        'AKADEMI',
        'AKADEMI KOMUNITAS',
        'NAMA PERGURUAN TINGGI',
    ];

    public function __construct(
        private AlumniImport $parent,
        private array $config,
        private string $sheetName,
        private int $headingRowNumber = 1,
    ) {}

    public function headingRow(): int
    {
        return $this->headingRowNumber;
    }

    public function collection(Collection $rows): void
    {
        $stopKeywords = [
            'MENGETAHUI',
            'KEPALA SEKOLAH',
            'NIP.',
            'NIP ',
            'PERSEBARAN',
            'JUMLAH',
            'TOTAL',
            'REKAP',
            'GURU BK',
            'WALI KELAS',
            'KOORDINATOR',
            'DAFTAR PERGURUAN TINGGI',
            '=UNIQUE',
            'DAFTAR PT',
            'NAMA PERGURUAN TINGGI',
        ];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 1;

            $namaRaw = $this->getValue($row, $this->config['nama']);
            $namaStr = strtoupper(trim((string) $namaRaw));

            foreach ($stopKeywords as $kw) {
                if ($namaStr !== '' && str_contains($namaStr, $kw)) {
                    return;
                }
            }

            if ($namaStr === '') {
                continue;
            }

            try {
                $this->processRow($row, $rowNumber);
            } catch (\Throwable $e) {
                $this->parent->errors[] = [
                    'sheet' => $this->sheetName,
                    'baris' => $rowNumber,
                    'pesan' => $e->getMessage(),
                ];
            }
        }
    }

    private function processRow(Collection $row, int $rowNumber): void
    {
        $namaRaw = $this->getValue($row, $this->config['nama']);
        if (! $namaRaw) {
            throw new \Exception('Nama kosong');
        }

        $nama = $this->normalizeName((string) $namaRaw);

        $siswa = Siswa::whereRaw('UPPER(TRIM(nama_lengkap)) = ?', [$nama])->first();
        if (! $siswa) {
            throw new \Exception("Siswa tidak ditemukan: {$namaRaw}");
        }

        $ptRaw    = (string) $this->getValue($row, $this->config['pt']);
        $prodiRaw = (string) $this->getValue($row, $this->config['prodi']);

        if (trim($ptRaw) === '' || trim($prodiRaw) === '') {
            throw new \Exception('Nama PT atau prodi kosong');
        }

        [$ptRaw, $prodiRaw] = $this->detectSwappedColumns($ptRaw, $prodiRaw);

        [$strata, $pt, $prodi] = $this->extractStrata(
            $ptRaw,
            $prodiRaw,
            $this->config['strata'] ? $this->getValue($row, $this->config['strata']) : null,
            $this->config['strata_from'] ?? null,
            $this->config
        );

        $perguruanTinggi = PerguruanTinggi::firstOrCreate(
            ['nama_pt' => $pt],
            ['jenis_pt' => null, 'kode_pt' => null, 'lokasi' => null]
        );

        $pilihanKe = $this->parseInt($this->getValue($row, $this->config['pilihan_ke'] ?? null));

        $kontak = $this->getValue($row, $this->config['kontak_wa'] ?? null);
        $kontak = $kontak ? preg_replace('/[^0-9]/', '', (string) $kontak) : null;

        $kipkRaw = $this->getValue($row, $this->config['kipk'] ?? null);
        $isKipk = $kipkRaw && (Str::upper(trim((string) $kipkRaw)) === 'YA' || str_contains(Str::upper(trim((string) $kipkRaw)), 'KIP'));

        $subJalur = $this->getValue($row, $this->config['sub_jalur'] ?? null);
        $subJalur = $subJalur ? trim((string) $subJalur) : null;

        $skorUtbk = $this->parseFloat($this->getValue($row, $this->config['skor_utbk'] ?? null));

        $subJalur = $this->getValue($row, $this->config['sub_jalur'] ?? null);
        $subJalur = $subJalur ? trim((string) $subJalur) : null;

        $nilai = [
            'strata_pendidikan'       => $strata ?? 'S1',
            'pilihan_ke'              => $pilihanKe,
            'status_penerimaan'       => 'diterima',
            'nilai_rapor_pendaftaran' => null,
            'skor_utbk'               => $skorUtbk,
            'kontak_wa_terbaru'       => $kontak,
            'is_pelamar_kipk'         => $isKipk,
            'izinkan_tampil_di_buku'  => true,
            'tanggal_pengisian'       => Carbon::now()->toDateString(),
        ];

        if ($subJalur) {
            $nilai['sub_jalur_mandiri'] = $subJalur;
        }

        if ($this->parent->bukuAlumniId) {
            $nilai['buku_alumni_id'] = $this->parent->bukuAlumniId;
        }

        StudyTracer::updateOrCreate(
            [
                'siswa_id'            => $siswa->id,
                'perguruan_tinggi_id' => $perguruanTinggi->id,
                'program_studi'       => $prodi,
                'jalur_masuk'         => $this->config['jalur_masuk'],
            ],
            $nilai
        );

        $this->parent->successCount++;
    }

    private function getValue(Collection $row, ?string $key): mixed
    {
        if (! $key) {
            return null;
        }

        if ($row->has($key)) {
            return $row->get($key);
        }

        $normalized = Str::slug($key, '_');
        return $row->get($normalized);
    }

    private function normalizeName(string $name): string
    {
        $name = str_replace('*', '', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        return trim(Str::upper($name));
    }

    private function looksLikeUniversity(string $value): bool
    {
        $upper = Str::upper(trim($value));
        foreach (self::UNIV_KEYWORDS as $kw) {
            if (str_contains($upper, $kw)) {
                return true;
            }
        }
        return false;
    }

    private function detectSwappedColumns(string $ptRaw, string $prodiRaw): array
    {
        $ptIsUniv    = $this->looksLikeUniversity($ptRaw);
        $prodiIsUniv = $this->looksLikeUniversity($prodiRaw);

        if (! $ptIsUniv && $prodiIsUniv) {
            return [$prodiRaw, $ptRaw];
        }

        return [$ptRaw, $prodiRaw];
    }

    private function extractStrata(
        string $ptRaw,
        string $prodiRaw,
        ?string $strataRaw,
        ?string $strataFrom,
        array $config
    ): array {
        $strata = null;
        $pt     = trim($ptRaw);
        $prodi  = trim($prodiRaw);

        if ($strataRaw) {
            $strata = Str::upper(trim($strataRaw));
        }

        if ($strataFrom) {
            $sourceKey = Str::slug($strataFrom, '_');

            $checkAndStrip = function (string $value) use (&$strata): string {
                if (preg_match('/^(S1|D4|D3|S2|S3)\s+(.+)$/i', trim($value), $m)) {
                    $strata = Str::upper($m[1]);
                    return trim($m[2]);
                }
                return trim($value);
            };

            if ($sourceKey === Str::slug($config['pt'], '_')) {
                $pt = $checkAndStrip($pt);
            }
            if ($sourceKey === Str::slug($config['prodi'], '_')) {
                $prodi = $checkAndStrip($prodi);
            }
        }

        return [$strata, $pt, $prodi];
    }

    private function parseInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === '-') {
            return null;
        }
        $n = (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);
        return $n > 0 ? $n : null;
    }

    private function parseFloat(mixed $value): ?float
    {
        if ($value === null || $value === '' || $value === '-') {
            return null;
        }
        $value = str_replace(',', '.', (string) $value);
        $f = (float) filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        return $f > 0 ? $f : null;
    }
}

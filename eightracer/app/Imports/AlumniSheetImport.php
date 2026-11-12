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
            'KETERANGAN',
        ];

        foreach ($rows as $index => $row) {
            // HeadingRow berada di baris Excel yang terdeteksi oleh AlumniImport.
            $rowNumber = $this->headingRowNumber + (int) $index + 1;

            $namaRaw = $this->getValue($row, $this->config['nama'] ?? null);
            $namaStr = strtoupper(trim((string) $namaRaw));

            // Hentikan hanya ketika memasuki bagian rekap/keterangan,
            // bukan setelah sejumlah baris data tertentu.
            foreach ($stopKeywords as $kw) {
                if ($namaStr !== '' && str_contains($namaStr, $kw)) {
                    return;
                }
            }

            if ($namaStr === '') {
                continue;
            }

            try {
                \Illuminate\Support\Facades\DB::transaction(fn () => $this->processRow($row, $rowNumber));
            } catch (\Throwable $e) {
                $this->parent->errors[] = [
                    'sheet' => $this->sheetName,
                    'baris' => $rowNumber,
                    'pesan' => 'Baris tidak sesuai template atau data siswa tidak cocok.',
                ];
            }
        }
    }

    private function processRow(Collection $row, int $rowNumber): void
    {
        foreach ($row as $cell) {
            if (is_string($cell) && (mb_strlen($cell) > 255 || ! \App\Support\PlainText::safe($cell))) {
                throw new \InvalidArgumentException('Invalid cell');
            }
        }
        $namaRaw = $this->getValue($row, $this->config['nama']);
        if (! $namaRaw) {
            throw new \Exception('Nama kosong');
        }

        $nama = $this->normalizeName((string) $namaRaw);
        if (($this->config['mode'] ?? null) === 'update_skor') {
            $this->updateSkorOnly($row, $nama, $rowNumber);
            return;
        }

        $matches = Siswa::whereRaw('UPPER(TRIM(nama_lengkap)) = ?', [$nama])->limit(2)->get();
        if ($matches->count() !== 1) {
            throw new \InvalidArgumentException('Student identity missing or ambiguous');
        }
        $siswa = $matches->first();

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
        $isKipk = in_array(Str::upper(trim((string) $kipkRaw)), ['YA', 'KIP', 'KIP-K', 'KIP KULIAH'], true);

        $subJalur = $this->getValue($row, $this->config['sub_jalur'] ?? null);
        $subJalur = $subJalur ? trim((string) $subJalur) : null;

        // $skorUtbk = $this->parseFloat($this->getValue($row, $this->config['skor_utbk'] ?? null));

        // 1. Ambil nilai mentah RATA2
        $rawRata2 = $this->getValue($row, $this->config['skor_utbk'] ?? null);


        // 2. Coba konversi ke angka
        $skorUtbk = $this->parseFloat($rawRata2);

        // 3. Jika gagal (karena berisi rumus Excel), hitung manual dari kolom pendukung
        if ($skorUtbk === null && is_string($rawRata2) && str_contains($rawRata2, '=')) {
            $kpu = $this->parseFloat($row->get('kpu'));
            $kk  = $this->parseFloat($row->get('kk'));
            $ppu = $this->parseFloat($row->get('ppu'));
            $kbm = $this->parseFloat($row->get('kbm'));
            $lbinSa = $this->parseFloat($row->get('lbin_saintek'));
            $lbinSo = $this->parseFloat($row->get('lbin_soshum'));
            $lbig = $this->parseFloat($row->get('lbig'));
            $pm = $this->parseFloat($row->get('pm'));

            // Kumpulkan nilai yang tidak null
            $values = array_filter([$kpu, $kk, $ppu, $kbm, $lbinSa, $lbinSo, $lbig, $pm], fn($val) => $val !== null);

            // Hitung rata-rata (sesuai rumus Excel: dibagi 8)
            if (count($values) > 0) {
                $skorUtbk = round(array_sum($values) / 8, 5);
            }
        }


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
            'izinkan_tampil_di_buku'  => false,
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

    private function getSkorDirectFromExcel($namaSiswa)
    {
        // Path file Excel yang sedang di-upload (didapat dari controller)
        // Kita asumsikan file disimpan sementara
        $filePath = $this->parent->getFilePath();
        if (!file_exists($filePath)) return null;

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
        // Cari sheet yang namanya mengandung "SKOR UTBK"
        $sheet = null;
        foreach ($spreadsheet->getSheetNames() as $sheetName) {
            if (str_contains(strtoupper($sheetName), 'SKOR UTBK')) {
                $sheet = $spreadsheet->getSheetByName($sheetName);
                break;
            }
        }

        if (!$sheet) return null;

        // Cari baris yang mengandung nama siswa
        $highestRow = $sheet->getHighestRow();
        for ($row = 1; $row <= $highestRow; $row++) {
            // Asumsi nama ada di kolom B (index 2)
            $cellName = $sheet->getCell('B' . $row)->getValue();
            if (strtoupper(trim($cellName)) === strtoupper(trim($namaSiswa))) {
                // Asumsi RATA2 ada di kolom O (index 15)
                $skorCell = $sheet->getCell('O' . $row)->getValue();
                if (is_numeric($skorCell) && $skorCell > 0) {
                    return (float) $skorCell;
                }
            }
        }
        return null;
    }

    private function updateSkorOnly(Collection $row, string $nama, int $rowNumber): void
    {
        $skorMentah = $this->getValue(
            $row,
            $this->config['skor_utbk'] ?? 'RATA2'
        );

        $skorUtbk = $this->parseFloat($skorMentah);

        // Jika RATA2 berupa formula atau tidak memiliki cached value,
        // hitung dari delapan komponen UTBK.
        if ($skorUtbk === null) {
            $skorUtbk = $this->calculateScoreFromComponents($row);
        }

        // Baris tanpa skor tidak boleh mengosongkan atau mengubah data lama.
        if ($skorUtbk === null || $skorUtbk <= 0) {
            return;
        }

        $namaLookup = $nama;

        // Alias eksplisit untuk variasi ejaan yang sudah ditemukan saat
        // mencocokkan file Excel dengan database.
        $nameAliases = [
            'M ZUKHRUF DANISH P S' => 'M ZUKHRUF DANIS P S',
        ];
        $namaLookup = $nameAliases[$namaLookup] ?? $namaLookup;

        // Nama siswa tidak selalu unik di tabel siswas. Kumpulkan semua
        // siswa dengan nama sama, lalu cocokkan tracer berdasarkan jalur.
        $siswaMatches = Siswa::whereRaw(
            'UPPER(TRIM(nama_lengkap)) = ?',
            [$namaLookup]
        )->get();

        if ($siswaMatches->isEmpty()) {
            throw new \Exception("Siswa tidak ditemukan: {$nama}");
        }

        $jalurMasuk = $this->config['jalur_masuk'];

        // Prodi/PTN menjadi petunjuk tambahan bila tersedia pada konfigurasi.
        $prodiRaw = trim((string) $this->getValue(
            $row,
            $this->config['prodi'] ?? null
        ));
        $ptnRaw = trim((string) $this->getValue(
            $row,
            $this->config['pt'] ?? null
        ));

        $baseQuery = StudyTracer::whereIn('siswa_id', $siswaMatches->pluck('id'))
            ->where('jalur_masuk', $jalurMasuk);

        // Sheet yang diproses adalah sheet skor UTBK. Untuk MANDIRI,
        // pilih sub-jalur SKOR UTBK, bukan tracer RAPOT atau sub-jalur lain.
        if (Str::upper(trim((string) $jalurMasuk)) === 'MANDIRI') {
            $baseQuery->whereRaw(
                "UPPER(TRIM(COALESCE(sub_jalur_mandiri, ''))) LIKE ?",
                ['%SKOR UTBK%']
            );
        }

        $baseMatches = $baseQuery->get();

        if ($baseMatches->isEmpty()) {
            throw new \Exception(
                "Study tracer jalur {$jalurMasuk} tidak ditemukan untuk {$nama}"
            );
        }

        $normalize = static function (?string $value): string {
            $value = Str::upper(trim((string) $value));
            $value = preg_replace('/[^A-Z0-9]+/u', ' ', $value);
            return trim(preg_replace('/\s+/', ' ', $value));
        };

        $selected = collect();

        // Prioritas pertama: prodi yang sama persis setelah normalisasi.
        if ($prodiRaw !== '') {
            $normalizedProdi = $normalize($prodiRaw);
            $selected = $baseMatches->filter(
                fn($item) => $normalize($item->program_studi) === $normalizedProdi
            )->values();
        }

        // Jika prodi tidak cocok atau menghasilkan beberapa kandidat,
        // gunakan PTN dari Excel untuk mempersempit pilihan.
        if ($selected->count() !== 1 && $ptnRaw !== '') {
            $normalizedPtn = $normalize($ptnRaw);
            $ptMatches = $baseMatches->filter(function ($item) use ($normalizedPtn, $normalize) {
                $namaPtn = $item->perguruanTinggi?->nama_pt;
                return $namaPtn !== null && $normalize($namaPtn) === $normalizedPtn;
            })->values();

            if ($ptMatches->count() === 1) {
                $selected = $ptMatches;
            } elseif ($ptMatches->count() > 1) {
                $selected = $ptMatches;
            }
        }

        // Jika tidak ada petunjuk yang cocok, satu-satunya tracer milik siswa
        // pada jalur ini aman dipilih. Jika lebih dari satu, jangan menebak.
        if ($selected->isEmpty() && $baseMatches->count() === 1) {
            $selected = $baseMatches;
        }

        // Jika beberapa tracer masih cocok, update massal hanya diizinkan
        // bila identitas PTN dan prodi benar-benar sama.
        if ($selected->count() > 1) {
            $identityKeys = $selected->map(function ($item) use ($normalize) {
                return (string) $item->perguruan_tinggi_id . '|'
                    . $normalize($item->program_studi);
            })->unique();

            if ($identityKeys->count() !== 1) {
                throw new \Exception(
                    "Ditemukan beberapa study tracer {$jalurMasuk} untuk {$nama}; "
                        . "prodi/PTN tidak cukup untuk menentukan baris yang benar. Update dibatalkan"
                );
            }
        }

        if ($selected->isEmpty()) {
            $detail = $prodiRaw !== '' ? " (prodi Excel: {$prodiRaw})" : '';
            throw new \Exception(
                "Study tracer {$jalurMasuk} untuk {$nama} tidak dapat dicocokkan secara aman{$detail}"
            );
        }

        if ($selected->pluck('siswa_id')->unique()->count() !== 1) {
            throw new \InvalidArgumentException('Student identity missing or ambiguous');
        }

        foreach ($selected as $studyTracer) {
            $studyTracer->update(['skor_utbk' => $skorUtbk]);
        }

        $this->parent->successCount++;
    }

    /**
     * Hitung rata-rata 8 komponen UTBK jika nilai RATA2 tidak tersedia.
     * Pembagi tetap 8 mengikuti rumus pada file Excel sumber.
     */
    private function calculateScoreFromComponents(Collection $row): ?float
    {
        $componentKeys = [
            'kpu',
            'kk',
            'ppu',
            'kbm',
            'lbin_saintek',
            'lbin_soshum',
            'lbig',
            'pm',
        ];

        $total = 0.0;
        $hasValue = false;

        foreach ($componentKeys as $key) {
            $value = $this->parseFloat($row->get($key));

            if ($value !== null) {
                $total += $value;
                $hasValue = true;
            }
        }

        if (! $hasValue || $total <= 0) {
            return null;
        }

        return round($total / 8, 5);
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

        if (is_string($value)) {
            $value = trim($value);

            // Contoh format pada file: "477, 60" menjadi "477.60".
            $value = preg_replace('/\s+/', '', $value);
            $value = str_replace(',', '.', $value);

            // Abaikan teks/hasil error rumus seperti #VALUE!.
            if (! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
                return null;
            }
        }

        if (! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        return $number > 0 ? $number : null;
    }
}

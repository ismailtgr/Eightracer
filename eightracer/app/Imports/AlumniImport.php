<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\IOFactory;

class AlumniImport implements \Maatwebsite\Excel\Concerns\Import, WithMultipleSheets
{
    public array $errors = [];
    public int $successCount = 0;
    public ?int $bukuAlumniId = null;
    public array $onlySheets = [];

    private ?string $filePath = null;

    public function setFilePath(string $path): void
    {
        $this->filePath = $path;
    }

        public function getFilePath()
    {
        return $this->filePath;
    }

    public function sheets(): array
    {
        $mapping = config('alumni_import.sheets', []);
        $imports = [];

        $availableSheets = [];
        $headingRows = [];

        if ($this->filePath && file_exists($this->filePath)) {
            $reader = IOFactory::createReaderForFile($this->filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($this->filePath);

            foreach ($spreadsheet->getSheetNames() as $name) {
                $availableSheets[] = $name;

                $sheet = $spreadsheet->getSheetByName($name);
                $headingRow = 1;
                $maxScan = min(15, $sheet->getHighestRow()); // Pindai 15 baris pertama

                for ($r = 1; $r <= $maxScan; $r++) {
                    $rowValues = [];
                    foreach ($sheet->getRowIterator($r, $r) as $row) {
                        foreach ($row->getCellIterator() as $cell) {
                            $rowValues[] = strtoupper(trim((string) $cell->getValue()));
                        }
                    }

                    $hasNama = false;
                    $hasProdi = false;

                    foreach ($rowValues as $val) {
                        if (str_contains($val, 'NAMA')) {
                            $hasNama = true;
                        }
                        if (str_contains($val, 'PROGRAM STUDI') || str_contains($val, 'JURUSAN')) {
                            $hasProdi = true;
                        }
                    }

                    // SYARAT MUTLAK: Harus ada kolom NAMA dan PROGRAM STUDI di baris yang sama
                    if ($hasNama && $hasProdi) {
                        $headingRow = $r;
                        break;
                    }
                }
                $headingRows[$name] = $headingRow;
            }

            $spreadsheet->disconnectWorksheets();
        }

        foreach ($mapping as $sheetName => $config) {
            $matched = null;
            foreach ($availableSheets as $available) {
                if (strtoupper(trim($available)) === strtoupper(trim($sheetName))) {
                    $matched = $available;
                    break;
                }
            }

            if ($matched === null) {
                continue;
            }

            if ($this->onlySheets !== [] && ! in_array(
                strtoupper(trim($matched)),
                array_map(fn($s) => strtoupper(trim((string) $s)), $this->onlySheets),
                true
            )) {
                continue;
            }

            $imports[$matched] = new AlumniSheetImport(
                $this,
                $config,
                $matched,
                $headingRows[$matched] ?? 1
            );
        }

        if (empty($imports)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['file' => 'Nama sheet tidak sesuai template impor sekolah.']);
        }

        return $imports;
    }
}

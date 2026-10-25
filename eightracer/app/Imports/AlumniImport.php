<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\IOFactory;

class AlumniImport implements Import, WithMultipleSheets
{
    public array $errors = [];
    public int $successCount = 0;

    private ?string $filePath = null;

    public function setFilePath(string $path): void
    {
        $this->filePath = $path;
    }

    public function sheets(): array
    {
        $mapping = config('alumni_import.sheets', []);
        $imports = [];

        $availableSheets = [];
        $headingRows = [];

        if ($this->filePath && file_exists($this->filePath)) {
            $spreadsheet = IOFactory::load($this->filePath);

            foreach ($spreadsheet->getSheetNames() as $name) {
                $availableSheets[] = $name;

                $sheet = $spreadsheet->getSheetByName($name);
                $headingRow = 1;
                $maxScan = min(10, $sheet->getHighestRow());

                // Kata kunci yang biasanya ada di baris header
                $headerKeywords = ['NAMA', 'NO', 'KLS', 'KELAS', 'JENJANG', 'PROGRAM STUDI', 'PERGURUAN TINGGI', 'PIL', 'NOMOR WA', 'KIPK', 'JURUSAN'];

                for ($r = 1; $r <= $maxScan; $r++) {
                    $rowValues = [];
                    foreach ($sheet->getRowIterator($r, $r) as $row) {
                        foreach ($row->getCellIterator() as $cell) {
                            $rowValues[] = strtoupper(trim((string) $cell->getValue()));
                        }
                    }

                    // Hitung berapa kata kunci header yang cocok di baris ini
                    $matchCount = 0;
                    foreach ($headerKeywords as $kw) {
                        foreach ($rowValues as $val) {
                            if ($val !== '' && str_contains($val, $kw)) {
                                $matchCount++;
                                break;
                            }
                        }
                    }

                    // Baris dianggap header jika cocok minimal 3 kata kunci
                    if ($matchCount >= 3) {
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

            $imports[$matched] = new AlumniSheetImport(
                $this,
                $config,
                $matched,
                $headingRows[$matched] ?? 1
            );
        }

        if (empty($imports)) {
            throw new \Exception('Tidak ada sheet yang cocok dengan config. Sheet tersedia: ' . implode(', ', $availableSheets));
        }

        return $imports;
    }
}
<?php

namespace App\Services\School;

use App\Services\Security\ClamAvScanner;
use App\Services\Security\SpreadsheetGuard;
use App\Support\PlainText;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class BulkSpreadsheetReader
{
    public bool $date1904 = false;

    private function reject(string $message = 'Gunakan template CSV UTF-8 atau XLSX tanpa formula dan isi aktif.'): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }

    public function read(UploadedFile $file, string $delimiter = 'comma'): array
    {
        if ($file->getSize() > 5 * 1024 * 1024) {
            $this->reject();
        }
        $ext = strtolower($file->getClientOriginalExtension());
        if ($ext === 'xlsx') {
            app(SpreadsheetGuard::class)->inspect($file, config('b4.import_sheets', 50));
            $sheets = $this->xlsx($file->getRealPath());
        } elseif ($ext === 'csv') {
            app(ClamAvScanner::class)->assertClean($file->getRealPath());
            $raw = file_get_contents($file->getRealPath());
            if (! is_string($raw) || ! mb_check_encoding($raw, 'UTF-8') || str_contains($raw, "\0")) {
                $this->reject();
            }
            $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
            $stream = fopen('php://temp', 'w+');
            fwrite($stream, $raw);
            rewind($stream);
            $rows = [];
            $i = 0;
            $separator = ['comma' => ',', 'semicolon' => ';', 'tab' => "\t"][$delimiter];
            while (($row = fgetcsv($stream, 0, $separator, '"', '')) !== false) {
                if (++$i > config('b4.import_rows') + 10 || count($row) > 52) {
                    fclose($stream);
                    $this->reject('Batas impor 2.000 baris data dan 52 kolom.');
                }
                $rows[$i] = $row;
            }
            fclose($stream);
            $sheets = ['CSV' => $rows];
        } else {
            $this->reject();
        }
        $count = $cells = 0;
        foreach ($sheets as $rows) {
            foreach ($rows as $row) {
                if (++$count > config('b4.import_rows') + 10 * count($sheets)) {
                    $this->reject();
                }
                foreach ($row as $value) {
                    if (++$cells > 50000 || mb_strlen((string) $value) > 10000 || ! PlainText::safe((string) $value)
                        || preg_match('/^[\x00-\x20]*[=+@]/u', (string) $value)) {
                        $this->reject();
                    }
                }
            }
        }

        return $sheets;
    }

    private function xml(string|false $xml): \SimpleXMLElement
    {
        if (! is_string($xml)) {
            $this->reject();
        }
        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) {
            $this->reject();
        }
        $old = libxml_use_internal_errors(true);
        try {
            $parsed = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($old);
        }
        if ($parsed === false) {
            $this->reject();
        }

        return $parsed;
    }

    private function xlsx(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            $this->reject();
        }
        try {
            $book = $this->xml($zip->getFromName('xl/workbook.xml'));
            $properties = $book->xpath('//*[local-name()="workbookPr"]');
            $this->date1904 = in_array((string) ($properties[0]['date1904'] ?? ''), ['1', 'true'], true);
            $rels = $this->xml($zip->getFromName('xl/_rels/workbook.xml.rels'));
            $targets = [];
            foreach ($rels->xpath('//*[local-name()="Relationship"]') as $rel) {
                $target = (string) $rel['Target'];
                $target = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target;
                if (preg_match('#^xl/worksheets/[A-Za-z0-9_-]+\.xml$#D', $target)) {
                    $targets[(string) $rel['Id']] = $target;
                }
            }
            $shared = [];
            $raw = $zip->getFromName('xl/sharedStrings.xml');
            if (is_string($raw)) {
                foreach ($this->xml($raw)->xpath('//*[local-name()="si"]') as $si) {
                    $shared[] = implode('', array_map('strval', $si->xpath('.//*[local-name()="t"]')));
                }
            }
            $sheets = [];
            foreach ($book->xpath('//*[local-name()="sheet"]') as $sheet) {
                $id = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                if (! isset($targets[$id])) {
                    $this->reject();
                }
                $rows = [];
                foreach ($this->xml($zip->getFromName($targets[$id]))->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') as $row) {
                    $values = [];
                    foreach ($row->xpath('./*[local-name()="c"]') as $cell) {
                        preg_match('/^([A-Z]+)[0-9]+$/', (string) $cell['r'], $matches);
                        $col = 0;
                        foreach (str_split($matches[1]) as $letter) {
                            $col = $col * 26 + ord($letter) - 64;
                        }
                        $value = (string) ($cell->xpath('./*[local-name()="v"]')[0] ?? '');
                        if ((string) $cell['t'] === 's') {
                            $value = $shared[(int) $value] ?? '';
                        }
                        if ((string) $cell['t'] === 'inlineStr') {
                            $value = implode('', array_map('strval', $cell->xpath('.//*[local-name()="t"]')));
                        }
                        $values[$col - 1] = $value;
                    }
                    $rows[(int) $row['r']] = $values;
                }
                $sheets[(string) $sheet['name']] = $rows;
            }

            return $sheets;
        } finally {
            $zip->close();
        }
    }
}

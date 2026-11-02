<?php
namespace App\Services\Security;

use App\Support\PlainText;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class SpreadsheetGuard
{
    public function __construct(private ClamAvScanner $scanner) {}

    public function inspect(UploadedFile $file): void
    {
        // Current import contract uses named worksheets. CSV/XLS are intentionally closed.
        if (strtolower($file->getClientOriginalExtension()) !== 'xlsx' || $file->getSize() > config('security.upload_bytes')) {
            $this->reject();
        }
        $this->scanner->assertClean($file->getRealPath());
        $zip = new ZipArchive;
        if ($zip->open($file->getRealPath(), ZipArchive::RDONLY) !== true) {
            $this->reject();
        }
        try {
            if ($zip->numFiles > 500 || $zip->locateName('xl/workbook.xml') === false || $zip->locateName('[Content_Types].xml') === false) {
                $this->reject();
            }
            $total = 0;
            $rows = 0;
            $cells = 0;
            $sheets = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = $stat['name'];
                $total += $stat['size'];
                if ($total > 20 * 1024 * 1024 || ($stat['encryption_method'] ?? 0) !== 0
                    || preg_match('#(^/|\.\.|\\\\|vbaproject|macros|externallinks|embeddings|\.bin$)#i', $name)) {
                    $this->reject();
                }
                if (! preg_match('/\.(xml|rels)$/i', $name)) {
                    continue;
                }
                $xml = $zip->getFromIndex($i);
                if (! is_string($xml) || preg_match('/<!DOCTYPE|<!ENTITY|macroEnabled/i', $xml)) {
                    $this->reject();
                }
                $reader = new \XMLReader;
                $reader->XML($xml, null, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
                $reader->setParserProperty(\XMLReader::SUBST_ENTITIES, false);
                $worksheet = preg_match('#^xl/worksheets/[^/]+\.xml$#', $name);
                if ($worksheet && ++$sheets > 10) { $this->reject(); }
                $previousErrors = libxml_use_internal_errors(true);
                try {
                    while ($reader->read()) {
                        if ($reader->nodeType === \XMLReader::ELEMENT) {
                            if ($reader->localName === 'f' || ($reader->localName === 'Relationship' && $reader->getAttribute('TargetMode') === 'External')) {
                                $this->reject();
                            }
                            if ($worksheet && $reader->localName === 'row' && ++$rows > config('security.import_max_rows') + 100) {
                                $this->reject();
                            }
                            if ($worksheet && $reader->localName === 'c') {
                                $ref = $reader->getAttribute('r');
                                if (++$cells > 50000 || ! is_string($ref) || ! $this->boundedReference($ref)) { $this->reject(); }
                            }
                            if ($worksheet && $reader->localName === 'dimension') {
                                foreach (explode(':', (string) $reader->getAttribute('ref')) as $ref) {
                                    if (! $this->boundedReference($ref)) { $this->reject(); }
                                }
                            }
                        }
                        if ($reader->nodeType === \XMLReader::TEXT && (! PlainText::safe($reader->value) || mb_strlen($reader->value) > 10000)) {
                            $this->reject();
                        }
                    }
                    if (libxml_get_errors()) { $this->reject(); }
                } finally {
                    $reader->close();
                    libxml_clear_errors();
                    libxml_use_internal_errors($previousErrors);
                }
            }
        } finally {
            $zip->close();
        }
    }

    private function reject(): never
    {
        throw ValidationException::withMessages(['file' => 'Gunakan template XLSX tanpa formula, macro, tautan eksternal, atau isi aktif; maksimal 5 MB dan 2.000 baris data.']);
    }

    private function boundedReference(string $ref): bool
    {
        if (! preg_match('/^([A-Z]{1,2})([1-9][0-9]{0,3})$/D', $ref, $matches)) { return false; }
        $column = 0;
        foreach (str_split($matches[1]) as $char) { $column = $column * 26 + ord($char) - 64; }
        return $column <= 52 && (int) $matches[2] <= config('security.import_max_rows') + 100;
    }
}

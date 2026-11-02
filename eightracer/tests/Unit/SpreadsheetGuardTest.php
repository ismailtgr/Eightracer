<?php
namespace Tests\Unit;
use App\Services\Security\ClamAvScanner;
use App\Services\Security\SpreadsheetGuard;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SpreadsheetGuardTest extends TestCase
{
    private array $files = [];
    protected function tearDown(): void
    {
        foreach ($this->files as $path) { @unlink($path); }
        parent::tearDown();
    }
    private function file(string $xml, array $extras = []): UploadedFile
    {
        $directory = storage_path('framework/testing');
        if (! is_dir($directory)) { mkdir($directory, 0700, true); }
        $path = $directory.'/'.bin2hex(random_bytes(16)).'.xlsx';
        $this->files[] = $path;
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach (['xl/workbook.xml' => '<workbook/>', '[Content_Types].xml' => '<Types/>', 'xl/worksheets/sheet1.xml' => $xml, ...$extras] as $name => $data) { $zip->addFromString($name, $data); }
        $zip->close();
        return new UploadedFile($path, 'template.xlsx', null, null, true);
    }
    private function cleanScanner(): ClamAvScanner
    {
        return new class extends ClamAvScanner { public function assertClean(string $path): void {} };
    }
    public function test_safe_xlsx_structure_passes_with_clean_scanner(): void
    {
        (new SpreadsheetGuard($this->cleanScanner()))->inspect($this->file('<worksheet><row r="1"><c r="A1"><v>123</v></c></row></worksheet>'));
        $this->assertTrue(true);
    }
    public function test_macro_formula_external_links_and_archive_traversal_are_rejected(): void
    {
        foreach ([
            ['<worksheet><row><c r="A1"><f>1+1</f></c></row></worksheet>', []],
            ['<worksheet/>', ['xl/vbaProject.bin' => 'synthetic']],
            ['<worksheet/>', ['../file.xml' => '<a/>']],
            ['<worksheet/>', ['xl/_rels/workbook.xml.rels' => '<Relationships><Relationship TargetMode="External" Target="https://example.test"/></Relationships>']],
            ['<!DOCTYPE foo [<!ENTITY x SYSTEM "file:///private">]><worksheet/>', []],
            ['<worksheet><row><c r="XFD999999"/></row></worksheet>', []],
            ['<worksheet><dimension ref="A1:XFD999999"/></worksheet>', []],
        ] as [$xml, $extras]) {
            try {
                (new SpreadsheetGuard($this->cleanScanner()))->inspect($this->file($xml, $extras));
                $this->fail('Unsafe spreadsheet accepted');
            } catch (ValidationException) { $this->assertTrue(true); }
        }
    }
    public function test_scanner_unavailable_fails_closed(): void
    {
        config(['security.clamav_endpoint' => 'tcp://not-allowed.example:3310']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionCode(0);
        (new SpreadsheetGuard(new ClamAvScanner))->inspect($this->file('<worksheet/>'));
    }
    public function test_infected_result_never_reaches_spreadsheet_parser(): void
    {
        $scanner = new class extends ClamAvScanner {
            public function assertClean(string $path): void { abort(422, 'File tidak lolos pemeriksaan keamanan.'); }
        };
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        (new SpreadsheetGuard($scanner))->inspect($this->file('<worksheet/>'));
    }
}

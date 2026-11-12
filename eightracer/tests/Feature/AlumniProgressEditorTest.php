<?php

namespace Tests\Feature;

use App\Models\AlumniProgress;
use App\Models\BasisDataBeasiswa;
use App\Models\SchoolImportBatch;
use App\Services\Security\ClamAvScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\PrdFixtures;
use Tests\TestCase;

class AlumniProgressEditorTest extends TestCase
{
    use PrdFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
    }

    private function payload(array $extra = []): array
    {
        return [...['periode' => 'Ganjil 2026/2027', 'semester' => 1, 'status_pendidikan' => 'aktif',
            'tanggal_update' => now('Asia/Jakarta')->toDateString(), 'ipk_terkini' => null,
            'penerima_kipk' => null, 'nominal_ukt' => null, 'nominal_ipi' => null, 'beasiswa' => []], ...$extra];
    }

    private function preview(int $id, string $csv, array $extra = [])
    {
        return $this->post('/api/alumni/'.$id.'/progress-imports',
            [...['file' => UploadedFile::fake()->createWithContent('progress.csv', $csv)], ...$extra], ['Accept' => 'application/json']);
    }

    private function finish(int $id, array $batch)
    {
        return $this->postJson('/api/alumni/'.$id.'/progress-imports/'.$batch['id'].'/commit', ['commit_digest' => $batch['commit_digest']]);
    }

    public function test_manual_create_edit_and_archive_persist_without_inventing_missing_values(): void
    {
        $admission = $this->admission($this->student(null, ['tahun_lulus' => 2024]));
        $this->signIn($this->account('administrator'));
        $url = '/api/alumni/'.$admission->id.'/college-progress';
        $first = $this->postJson($url, $this->payload())->assertCreated()->assertJsonPath('data.revision', 1)->json('data.id');
        $this->getJson('/api/b4/alumni/'.$admission->id)->assertOk()->assertJsonPath('data.status_terakhir', 'aktif')
            ->assertJsonPath('data.progress.0.ipk_terkini', null)->assertJsonPath('data.progress.0.nominal_ukt', null)
            ->assertJsonPath('data.progress.0.penerima_kipk', null)->assertJsonPath('data.progress.0.tanggal_update', now('Asia/Jakarta')->toDateString());
        $this->postJson($url, $this->payload())->assertConflict();
        $second = $this->putJson($url.'/'.$first, $this->payload(['status_pendidikan' => 'cuti', 'ipk_terkini' => 3.5, 'nominal_ukt' => 0, 'penerima_kipk' => false]))
            ->assertCreated()->assertJsonPath('data.revision', 2)->json('data.id');
        $this->assertFalse(AlumniProgress::findOrFail($first)->is_current);
        $this->assertSame($first, AlumniProgress::findOrFail($second)->supersedes_id);
        $this->getJson('/api/b4/alumni/'.$admission->id)->assertOk()->assertJsonCount(1, 'data.progress')->assertJsonPath('data.status_terakhir', 'cuti');
        $this->deleteJson($url.'/'.$second)->assertOk();
        $this->assertDatabaseCount('alumni_progress', 2);
        $this->getJson('/api/b4/alumni/'.$admission->id)->assertOk()->assertJsonCount(0, 'data.progress')->assertJsonPath('data.status_terakhir', null);
        $this->postJson($url, $this->payload())->assertCreated()->assertJsonPath('data.revision', 3);
    }

    public function test_staff_auth_and_admission_scope_cannot_be_bypassed(): void
    {
        $admission = $this->admission($this->student(null, ['tahun_lulus' => 2024]));
        $other = $this->admission($this->student(null, ['tahun_lulus' => 2025]));
        $url = '/api/alumni/'.$admission->id.'/college-progress';
        $this->postJson($url, $this->payload())->assertUnauthorized();
        $this->signIn($this->account());
        $this->postJson($url, $this->payload())->assertForbidden();
        $this->getJson('/api/alumni/'.$admission->id.'/progress-template')->assertForbidden();
        $this->signIn($this->account('administrator'));
        $this->postJson($url, $this->payload(['siswa_id' => $other->siswa_id]))->assertUnprocessable();
        $this->postJson($url, $this->payload(['study_tracer_id' => $other->id]))->assertUnprocessable();
        $this->postJson($url, $this->payload(['is_published' => true]))->assertUnprocessable();
        $row = $this->postJson($url, $this->payload())->assertCreated()->json('data.id');
        $this->putJson('/api/alumni/'.$other->id.'/college-progress/'.$row, $this->payload())->assertNotFound();
        $this->deleteJson('/api/alumni/'.$other->id.'/college-progress/'.$row)->assertNotFound();
        $this->putJson($url.'/'.$row, $this->payload(['periode' => 'Genap 2026/2027']))->assertConflict();
        $this->assertDatabaseCount('alumni_progress', 1);
    }

    public function test_real_validation_rejects_future_dates_invalid_gpa_and_ipi_for_non_mandiri(): void
    {
        $admission = $this->admission($this->student(null, ['tahun_lulus' => 2024]), 'SNBP');
        $this->signIn($this->account('administrator'));
        $url = '/api/alumni/'.$admission->id.'/college-progress';
        foreach ([
            ['tanggal_update' => now('Asia/Jakarta')->addDay()->toDateString()],
            ['tanggal_update' => '2026-02-30'], ['ipk_terkini' => 4.01], ['semester' => 0],
            ['status_pendidikan' => 'unknown'], ['nominal_ukt' => -1], ['nominal_ipi' => 0],
        ] as $extra) $this->postJson($url, $this->payload($extra))->assertUnprocessable();
        $this->assertDatabaseCount('alumni_progress', 0);
    }

    public function test_template_is_header_only_and_contains_no_fake_student_or_progress_data(): void
    {
        $admission = $this->admission($this->student());
        $this->signIn($this->account('administrator'));
        $response = $this->get('/api/alumni/'.$admission->id.'/progress-template')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertSame(1, substr_count(trim($response->getContent()), "\n") + 1);
        $this->assertStringContainsString('periode,semester,status_pendidikan,tanggal_update', $response->getContent());
        $this->assertStringNotContainsString('Murid', $response->getContent());
    }

    public function test_scanned_csv_preview_does_not_write_until_explicit_commit_then_commit_is_idempotent(): void
    {
        $this->mock(ClamAvScanner::class)->shouldReceive('assertClean')->once()->andReturnNull();
        $admission = $this->admission($this->student(null, ['tahun_lulus' => 2024]));
        $this->signIn($this->account('administrator'));
        $csv = "periode,semester,status_pendidikan,tanggal_update,ipk,kipk,ukt,ipi\nGanjil 2026/2027,1,Aktif,".now('Asia/Jakarta')->toDateString().",3.75,Tidak,0,\n";
        $batch = $this->preview($admission->id, $csv)->assertCreated()->assertJsonPath('data.status', 'validated')
            ->assertJsonPath('data.sample.0.operation', 'tambah')->json('data');
        $this->assertDatabaseCount('alumni_progress', 0);
        $this->finish($admission->id, $batch)->assertOk()->assertJsonPath('data.status', 'completed')->assertJsonPath('data.receipt.processed', 1);
        $this->finish($admission->id, $batch)->assertOk()->assertJsonPath('data.receipt.processed', 1);
        $this->assertDatabaseCount('alumni_progress', 1);
        $row = AlumniProgress::firstOrFail();
        $this->assertEquals(3.75, $row->ipk_terkini);
        $this->assertFalse($row->penerima_kipk);
        $this->assertEquals(0, $row->nominal_ukt);
        $this->assertNull($row->nominal_ipi);
        $this->assertFalse($row->is_published);
    }

    public function test_xlsx_aliases_and_named_catalog_scholarship_are_saved_with_null_unknowns(): void
    {
        $this->mock(ClamAvScanner::class)->shouldReceive('assertClean')->once()->andReturnNull();
        $admission = $this->admission($this->student(null, ['tahun_lulus' => 2024]));
        $catalog = BasisDataBeasiswa::create(['nama_beasiswa' => 'Program Fixture', 'jenjang' => 'S1', 'is_active' => true]);
        $this->signIn($this->account('administrator'));
        $file = $this->workbook(['Progress' => [
            ['periode','semester','status_pendidikan','tanggal_update','ipk','kipk','ukt','beasiswa','status_beasiswa'],
            ['Ganjil 2026/2027',1,'Aktif',now('Asia/Jakarta')->toDateString(),'','','','Program Fixture','diterima'],
        ]]);
        $batch = $this->post('/api/alumni/'.$admission->id.'/progress-imports', ['file' => $file], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.status', 'validated')->json('data');
        $this->finish($admission->id, $batch)->assertOk();
        $row = AlumniProgress::firstOrFail();
        $this->assertNull($row->ipk_terkini);
        $this->assertNull($row->penerima_kipk);
        $this->assertNull($row->nominal_ukt);
        $this->assertDatabaseHas('alumni_progress_beasiswa', ['alumni_progress_id' => $row->id, 'beasiswa_id' => $catalog->id, 'status_beasiswa' => 'diterima']);
    }

    public function test_bad_row_or_another_admission_blocks_entire_import_and_batch_is_owner_scoped(): void
    {
        $this->mock(ClamAvScanner::class)->shouldReceive('assertClean')->andReturnNull();
        $admission = $this->admission($this->student(null, ['tahun_lulus' => 2024]));
        $other = $this->admission($this->student(null, ['tahun_lulus' => 2025]));
        $admin = $this->account('administrator'); $this->signIn($admin);
        $date = now('Asia/Jakarta')->toDateString();
        $header = "periode,semester,status_pendidikan,tanggal_update,study_tracer_id\n";
        $invalid = $this->preview($admission->id, $header."Ganjil,1,aktif,$date,{$admission->id}\nGenap,2,aktif,$date,{$other->id}\n")
            ->assertCreated()->assertJsonPath('data.status', 'invalid')->assertJsonPath('data.summary.errors.0.row', 3)->json('data');
        $this->finish($admission->id, $invalid)->assertConflict();
        $valid = $this->preview($admission->id, $header."Ganjil,1,aktif,$date,{$admission->id}\n")->assertCreated()->json('data');
        $this->finish($other->id, $valid)->assertNotFound();
        $this->signIn($this->account('administrator'));
        $this->finish($admission->id, $valid)->assertNotFound();
        $this->signIn($admin);
        $this->finish($admission->id, [...$valid, 'commit_digest' => str_repeat('0', 64)])->assertConflict();
        $this->assertDatabaseCount('alumni_progress', 0);
    }

    public function test_stale_preview_fails_atomically_and_new_preview_uses_correction_history(): void
    {
        $this->mock(ClamAvScanner::class)->shouldReceive('assertClean')->andReturnNull();
        $admission = $this->admission($this->student(null, ['tahun_lulus' => 2024]));
        $this->signIn($this->account('administrator'));
        $url = '/api/alumni/'.$admission->id.'/college-progress';
        $row = $this->postJson($url, $this->payload())->assertCreated()->json('data.id');
        $csv = "periode,semester,status_pendidikan,tanggal_update,ipk\nGanjil 2026/2027,1,aktif,".now('Asia/Jakarta')->toDateString().",3.75\n";
        $batch = $this->preview($admission->id, $csv)->assertCreated()->assertJsonPath('data.sample.0.operation', 'koreksi')->json('data');
        $this->putJson($url.'/'.$row, $this->payload(['ipk_terkini' => 3.2]))->assertCreated();
        $this->finish($admission->id, $batch)->assertConflict()->assertJsonPath('data.receipt.processed', 0);
        $this->assertDatabaseCount('alumni_progress', 2);
        $fresh = $this->preview($admission->id, $csv)->assertCreated()->json('data');
        $this->finish($admission->id, $fresh)->assertOk();
        $this->assertDatabaseCount('alumni_progress', 3);
        $this->assertSame(3, AlumniProgress::where('is_current', true)->firstOrFail()->revision);
    }

    public function test_bad_extensions_duplicate_aliases_and_oversized_semester_batch_are_rejected(): void
    {
        $this->mock(ClamAvScanner::class)->shouldReceive('assertClean')->andReturnNull();
        $admission = $this->admission($this->student(null, ['tahun_lulus' => 2024]));
        $this->signIn($this->account('administrator'));
        $this->post('/api/alumni/'.$admission->id.'/progress-imports', ['file' => UploadedFile::fake()->createWithContent('source.php', '<?php echo 1;')], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->preview($admission->id, "periode,ipk,ipk_terkini\nGanjil,3.5,3.5\n")->assertUnprocessable();
        $rows = "periode,semester,status_pendidikan,tanggal_update\n";
        for ($i=1; $i<=41; $i++) $rows .= "Periode $i,1,aktif,".now('Asia/Jakarta')->toDateString()."\n";
        $this->preview($admission->id, $rows)->assertUnprocessable();
        $this->assertDatabaseCount('alumni_progress', 0);
    }

    public function test_unavailable_or_virus_positive_scanner_never_accepts_preview(): void
    {
        $admission = $this->admission($this->student(null, ['tahun_lulus' => 2024]));
        $this->signIn($this->account('administrator'));
        $this->mock(ClamAvScanner::class)->shouldReceive('assertClean')->andThrow(new \Symfony\Component\HttpKernel\Exception\HttpException(503, 'Pemindai tidak tersedia.'));
        $this->preview($admission->id, "periode,semester\nGanjil,1\n")->assertStatus(503);
        $this->mock(ClamAvScanner::class)->shouldReceive('assertClean')->andThrow(new \Symfony\Component\HttpKernel\Exception\HttpException(422, 'File ditolak.'));
        $this->preview($admission->id, "periode,semester\nGanjil,1\n")->assertUnprocessable();
        $this->assertDatabaseCount('school_import_batches', 0);
        $this->assertDatabaseCount('alumni_progress', 0);
    }

    private function workbook(array $sheets): UploadedFile
    {
        $entries = [];
        $book = '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        $rels = '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $index = 0;
        foreach ($sheets as $name => $rows) {
            $index++;
            $book .= '<sheet name="'.htmlspecialchars($name, ENT_XML1).'" sheetId="'.$index.'" r:id="rId'.$index.'"/>';
            $rels .= '<Relationship Id="rId'.$index.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$index.'.xml"/>';
            $xml = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
            foreach ($rows as $i => $row) {
                $n = $i + 1;
                $xml .= '<row r="'.$n.'">';
                foreach ($row as $j => $value) {
                    $xml .= '<c r="'.chr(65 + $j).$n.'" t="inlineStr"><is><t>'.htmlspecialchars((string) $value, ENT_XML1).'</t></is></c>';
                }
                $xml .= '</row>';
            }
            $entries['xl/worksheets/sheet'.$index.'.xml'] = $xml.'</sheetData></worksheet>';
        }
        $entries['xl/workbook.xml'] = $book.'</sheets></workbook>';
        $entries['xl/_rels/workbook.xml.rels'] = $rels.'</Relationships>';
        $entries['[Content_Types].xml'] = '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/></Types>';
        // A stored ZIP fixture assembled in memory avoids host-specific temporary ZIP rename behavior.
        $bytes = $central = '';
        $offset = 0;
        foreach ($entries as $name => $content) {
            $size = strlen($content);
            $length = strlen($name);
            $crc = crc32($content);
            $local = pack('VvvvvvVVVvv', 0x04034B50, 20, 0, 0, 0, 0, $crc, $size, $size, $length, 0).$name.$content;
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014B50, 20, 20, 0, 0, 0, 0, $crc, $size, $size, $length, 0, 0, 0, 0, 0, $offset).$name;
            $bytes .= $local;
            $offset += strlen($local);
        }
        $bytes .= $central.pack('VvvvvVVv', 0x06054B50, 0, 0, count($entries), count($entries), strlen($central), $offset, 0);

        return UploadedFile::fake()->createWithContent('school.xlsx', $bytes);
    }
}

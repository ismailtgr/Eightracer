<?php
namespace Tests\Feature;

use App\Imports\AlumniImport;
use App\Imports\AlumniSheetImport;
use App\Models\StudyTracer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\PrdFixtures;
use Tests\TestCase;

class B3B4IntegrationTest extends TestCase
{
    use PrdFixtures, RefreshDatabase;

    public function test_b3_and_b4_directory_contracts_coexist_without_route_shadowing(): void
    {
        $record = $this->admission($this->student(null, ['tahun_lulus' => 2024]));
        $this->signIn($this->account('administrator'));
        $this->getJson('/api/alumni?per_page=500')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.nama', 'Murid Uji');
        $this->getJson('/api/alumni/'.$record->id)->assertOk()->assertJsonPath('id', $record->id);
        $this->getJson('/api/alumni/filter-options')->assertOk()->assertJsonCount(1, 'univ');
        $this->getJson('/api/alumni/statistik')->assertOk();
        $this->getJson('/api/alumni/progress')->assertOk();
        $this->getJson('/api/b4/alumni')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.nama_lengkap', 'Murid Uji');
        $this->getJson('/api/b4/alumni/'.$record->id)->assertOk()->assertJsonPath('data.id', $record->id);
        $this->getJson('/api/perguruan-tinggi?search=Universitas')->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_student_cannot_read_unrestricted_staff_directory_but_owns_b3_form(): void
    {
        $owner = $this->account();
        $student = $this->student($owner);
        $this->admission($student);
        $this->admission($this->student());
        $this->signIn($owner);
        foreach (['/api/alumni', '/api/alumni/1', '/api/alumni/statistik', '/api/b4/alumni'] as $url) {
            $this->getJson($url)->assertForbidden();
        }
        $this->getJson('/api/alumni-form/status')->assertOk()->assertJsonPath('siswa.id', $student->id);
        $this->getJson('/api/alumni-form/summary')->assertOk();
    }

    public function test_overlapping_migration_does_not_duplicate_or_rollback_shared_column(): void
    {
        $student = $this->student(null, ['tahun_lulus' => 2024]);
        $migration = require database_path('migrations/2026_11_02_103214_add_tahun_lulus_to_siswa_table.php');
        $migration->up();
        $migration->down();
        $this->assertTrue(Schema::hasColumn('siswa', 'tahun_lulus'));
        $this->assertEquals(2024, $student->fresh()->tahun_lulus);
    }

    public function test_b3_score_import_updates_existing_record_without_erasing_or_publishing_it(): void
    {
        $record = $this->admission($this->student(null, ['nama_lengkap' => 'Nama Tunggal']), 'SNBT');
        $record->update(['skor_utbk' => 600]);
        $parent = new AlumniImport;
        $import = new AlumniSheetImport($parent, ['mode' => 'update_skor', 'nama' => 'nama', 'skor_utbk' => 'rata2', 'jalur_masuk' => 'SNBT'], 'SKOR UTBK');
        $import->collection(collect([collect(['nama' => 'Nama Tunggal', 'rata2' => '720,50'])]));
        $this->assertSame([], $parent->errors);
        $this->assertEquals(720.5, $record->fresh()->skor_utbk);
        $import->collection(collect([collect(['nama' => 'Nama Tunggal', 'rata2' => '-'])]));
        $this->assertEquals(720.5, $record->fresh()->skor_utbk);
        $this->assertFalse($record->fresh()->izinkan_tampil_di_buku);
    }

    public function test_score_import_rejects_duplicate_student_identity_instead_of_updating_both(): void
    {
        foreach ([1, 2] as $n) {
            $student = $this->student(null, ['nama_lengkap' => 'Nama Sama']);
            if ($n === 1) $first = $this->admission($student, 'SNBT');
            else StudyTracer::create(['siswa_id' => $student->id, 'perguruan_tinggi_id' => $first->perguruan_tinggi_id,
                'strata_pendidikan' => 'S1', 'program_studi' => $first->program_studi, 'jalur_masuk' => 'SNBT', 'status_penerimaan' => 'diterima']);
        }
        $parent = new AlumniImport;
        $import = new AlumniSheetImport($parent, ['mode' => 'update_skor', 'nama' => 'nama', 'skor_utbk' => 'rata2', 'jalur_masuk' => 'SNBT'], 'SKOR UTBK');
        $import->collection(collect([collect(['nama' => 'Nama Sama', 'rata2' => 700])]));
        $this->assertCount(1, $parent->errors);
        $this->assertSame(0, StudyTracer::whereNotNull('skor_utbk')->count());
    }
}

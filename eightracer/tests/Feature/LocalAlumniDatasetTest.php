<?php
namespace Tests\Feature;

use App\Models\AlumniProgress;
use App\Services\School\LocalExampleRecords;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PrdFixtures;
use Tests\TestCase;

class LocalAlumniDatasetTest extends TestCase
{
    use RefreshDatabase, PrdFixtures;

    public function test_local_fixture_has_readable_progress_but_is_excluded_from_real_school_aggregates(): void
    {
        $notes = json_encode(['fixture_key' => 'b4-local-alumni-20261111', 'synthetic' => true]);
        $sample = $this->student(null, ['tahun_lulus' => 2024, 'catatan' => $notes, 'izinkan_analitik' => false]);
        $admission = $this->admission($sample);
        $admission->update(['izinkan_tampil_di_buku' => false]);
        AlumniProgress::create(['siswa_id' => $sample->id, 'study_tracer_id' => $admission->id, 'tahun_lulus' => 2024,
            'periode' => 'Ganjil 2026/2027', 'semester' => 5, 'status_pendidikan' => 'aktif', 'ipk_terkini' => 3.65,
            'nominal_ukt' => 2000000, 'penerima_kipk' => false, 'tanggal_update' => today(),
            'is_current' => true, 'is_published' => false, 'publish_financials' => true]);
        $real = $this->student(null, ['tahun_lulus' => 2024]);
        $this->admission($real);
        $this->assertTrue(LocalExampleRecords::isAlumniExample($sample));
        $this->assertFalse(LocalExampleRecords::isAlumniExample($real));
        $this->app->instance('env', 'local');
        config(['b4.local_student_preview' => true]);
        $this->signIn($this->account());
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1']);
        $this->getJson('/api/jejak-alumni/'.$admission->id)->assertOk()
            ->assertJsonPath('data.progress.0.semester', 5)->assertJsonPath('data.progress.0.nominal_ukt', '2000000.00')
            ->assertJsonCount(3, 'data.timeline')->assertDontSee('PRIVATE_PHONE')->assertDontSee($sample->nisn);
        $this->signIn($this->account('administrator'));
        $this->getJson('/api/analitik-beasiswa/sekolah')->assertOk()->assertJsonPath('data.summary.alumni', 1)
            ->assertJsonPath('data.financial.measured_alumni', 0);
        $this->getJson('/api/dashboard/ukt-ipk')->assertOk()->assertJsonPath('data.jumlah_alumni', 0);
        $this->signIn($this->account());
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.1']);
        $this->getJson('/api/jejak-alumni/'.$admission->id)->assertNotFound();
        $this->assertFalse((bool) $admission->fresh()->izinkan_tampil_di_buku);
    }
}

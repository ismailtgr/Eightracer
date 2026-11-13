<?php

namespace Tests\Feature;

use App\Models\AlumniProgress;
use App\Models\BasisDataBeasiswa;
use App\Models\ProgramEligibilitas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\PrdFixtures;
use Tests\TestCase;

class PrdScholarshipStagesTest extends TestCase
{
    use PrdFixtures,RefreshDatabase;

    private function catalog(string $stage): BasisDataBeasiswa
    {
        $b = BasisDataBeasiswa::create(['nama_beasiswa' => 'Beasiswa Uji '.$stage, 'jenjang' => $stage, 'is_active' => true, 'durasi' => 4, 'satuan_durasi' => 'tahun', 'periode_apply' => 'Lihat jadwal penyelenggara']);
        $b->kriteriaKelayakanBeasiswa()->create(['nama_kriteria' => 'TKA', 'tipe_kriteria' => 'nilai_tka', 'operator' => '>=', 'nilai_kriteria' => '80']);

        return $b;
    }

    private function fake(int $id): void
    {
        config(['gemini.api_key' => 'synthetic-test-key']);
        Http::preventStrayRequests();
        $text = json_encode(['rekomendasi' => [['beasiswa_id' => $id, 'alasan' => 'Evaluasi sesuai katalog.', 'persiapan' => ['Periksa persyaratan resmi.']]]]);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'status' => 'completed', 'steps' => [['type' => 'model_output', 'content' => [['type' => 'text', 'text' => $text]]]],
        ])]);
    }

    public function test_sma_can_prepare_for_college_scholarship_and_duration_snapshot_does_not_change(): void
    {
        $owner = $this->account();
        $s = $this->student($owner, ['nilai_tka' => 90]);
        $this->classroom($s);
        $b = $this->catalog('Kuliah');
        $this->fake($b->id);
        $admin = $this->account('administrator');
        $this->signIn($admin);
        $id = $this->postJson("/api/siswa/{$s->id}/rekomendasi-beasiswa/generate", ['target_jenjang' => 'Kuliah'])->assertCreated()->assertJsonPath('data.target_jenjang', 'Kuliah')->json('data.id');
        $this->patchJson("/api/rekomendasi-beasiswa/{$id}/review", ['status_review' => 'disetujui'])->assertOk();
        $b->update(['durasi' => 2]);
        $this->signIn($owner);
        $this->getJson('/api/rekomendasi-beasiswa/saya')->assertOk()->assertJsonPath('data.kandidat.0.beasiswa.konteks', 'Persiapan jenjang berikutnya')->assertJsonPath('data.kandidat.0.beasiswa.durasi', '2.00');
    }

    public function test_actual_college_progress_supplies_ipk_without_requiring_an_active_sma_class(): void
    {
        $s = $this->student(null, ['tahun_lulus' => 2026, 'nilai_tka' => 90]);
        $t = $this->admission($s);
        AlumniProgress::create(['siswa_id' => $s->id, 'study_tracer_id' => $t->id, 'tahun_lulus' => '2026', 'periode' => 'Semester 2', 'semester' => 2, 'status_pendidikan' => 'aktif', 'ipk_terkini' => 3.8, 'tanggal_update' => today(), 'is_current' => true]);
        $b = $this->catalog('Kuliah');
        $b->kriteriaKelayakanBeasiswa()->create(['nama_kriteria' => 'IPK', 'tipe_kriteria' => 'ipk', 'operator' => '>=', 'nilai_kriteria' => '3.5']);
        $this->fake($b->id);
        $this->signIn($this->account('administrator'));
        $this->postJson("/api/siswa/{$s->id}/rekomendasi-beasiswa/generate")->assertCreated()->assertJsonPath('data.target_jenjang', 'Kuliah')->assertJsonPath('data.rekomendasi_beasiswa_detail.0.is_recommended', true);
    }

    public function test_linked_program_changes_make_existing_ai_draft_stale_even_if_status_stays_eligible(): void
    {
        $s = $this->student(null, ['nilai_tka' => 90]);
        $this->classroom($s);
        $p = ProgramEligibilitas::create(['nama' => 'Program', 'kriteria' => [['nama_kriteria' => 'TKA', 'tipe_kriteria' => 'nilai_tka', 'operator' => '>=', 'nilai_kriteria' => '80']], 'is_active' => true]);
        $b = $this->catalog('SMA');
        $b->update(['program_eligibilitas_id' => $p->id]);
        $this->fake($b->id);
        $this->signIn($this->account('administrator'));
        $id = $this->postJson("/api/siswa/{$s->id}/rekomendasi-beasiswa/generate")->assertCreated()->json('data.id');
        $p->update(['version' => 2]);
        $this->patchJson("/api/rekomendasi-beasiswa/{$id}/review", ['status_review' => 'disetujui'])->assertStatus(409);
    }
}

<?php
namespace Tests\Feature;

use App\Models\BasisDataBeasiswa;
use App\Models\RekomendasiBeasiswa;
use App\Services\Beasiswa\GeminiScholarshipExplainer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\PrdFixtures;
use Tests\TestCase;

class SaveRecommendationWorkflowTest extends TestCase
{
    use RefreshDatabase, PrdFixtures;
    private function fixture(): array
    {
        config(['cache.default'=>'array']);
        $s=$this->student(null,['nilai_tka'=>90]); $this->classroom($s);
        $p=BasisDataBeasiswa::create(['nama_beasiswa'=>'Program terisolasi','jenjang'=>'SMA','is_active'=>true]);
        $p->kriteriaKelayakanBeasiswa()->create(['nama_kriteria'=>'TKA','tipe_kriteria'=>'nilai_tka','operator'=>'>=','nilai_kriteria'=>'80','bobot'=>1]);
        $this->mock(GeminiScholarshipExplainer::class, fn($m)=>$m->shouldNotReceive('explain'));
        return [$s,$p,"/api/siswa/{$s->id}/rekomendasi-beasiswa/save"];
    }
    public function test_save_without_provider_is_idempotent_and_preserves_optional_staff_edits(): void
    {
        [$s,$p,$path]=$this->fixture(); $this->signIn($this->account('administrator'));
        $one=$this->postJson($path,['target_jenjang'=>'SMA'])->assertOk()->assertJsonPath('meta.requires_review',false)->assertJsonPath('meta.saved',true);
        $record=RekomendasiBeasiswa::findOrFail($one->json('data.id'));
        $record->rekomendasiBeasiswaDetail()->first()->update(['alasan_rekomendasi'=>'Catatan opsional guru.']);
        $this->postJson($path,['target_jenjang'=>'SMA'])->assertOk()->assertJsonPath('data.id',$record->id)
            ->assertJsonPath('data.rekomendasi_beasiswa_detail.0.alasan_rekomendasi','Catatan opsional guru.');
        $this->assertDatabaseCount('rekomendasi_beasiswa',1);
        $this->assertDatabaseCount('rekomendasi_beasiswa_detail',1);
        $p->update(['deskripsi'=>'Kriteria program telah diperbarui.']);
        $two=$this->postJson($path,['target_jenjang'=>'SMA'])->assertOk();
        $this->assertNotEquals($record->id,$two->json('data.id'));
        $this->assertDatabaseCount('rekomendasi_beasiswa',2);
    }
    public function test_save_rejects_student_role_and_untrusted_fields(): void
    {
        [$s,$p,$path]=$this->fixture(); $this->postJson($path)->assertUnauthorized();
        $this->signIn($this->account('siswa')); $this->postJson($path)->assertForbidden();
        $this->signIn($this->account('administrator'));
        $this->postJson($path,['target_jenjang'=>'SMA','siswa_id'=>999])->assertUnprocessable();
        $this->postJson($path,['target_jenjang'=>'SMA','status_review'=>'disetujui'])->assertUnprocessable();
        $this->assertDatabaseCount('rekomendasi_beasiswa',0);
    }
    public function test_save_with_unknown_data_keeps_unknown_instead_of_inventing_a_score(): void
    {
        [$s,$p,$path]=$this->fixture(); $s->update(['nilai_tka'=>null]);
        $this->signIn($this->account('administrator'));
        $r=$this->postJson($path,['target_jenjang'=>'SMA'])->assertOk();
        $evaluation=$r->json('data.rekomendasi_beasiswa_detail.0.evaluasi_kriteria');
        $this->assertFalse($evaluation['lengkap']);
        $this->assertSame('belum_diketahui',$evaluation['checklist'][0]['status']);
    }
    public function test_save_respects_generate_lock(): void
    {
        [$s,$p,$path]=$this->fixture(); $this->signIn($this->account('administrator'));
        $lock=Cache::lock('scholarship-generate:'.$s->id,150); $this->assertTrue($lock->get());
        try { $this->postJson($path)->assertStatus(409); } finally { $lock->release(); }
        $this->assertDatabaseCount('rekomendasi_beasiswa',0);
    }
}

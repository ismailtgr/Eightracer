<?php

namespace Tests\Feature;

use App\Models\BasisDataBeasiswa;
use App\Models\RekomendasiBeasiswa;
use App\Services\Beasiswa\GeminiScholarshipExplainer;
use App\Services\Beasiswa\ScholarshipRecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\PrdFixtures;
use Tests\TestCase;

class GenerateWorkflowTest extends TestCase
{
    use RefreshDatabase, PrdFixtures;

    private function setupStudent(): array
    {
        config(['cache.default'=>'array']);
        $student=$this->student(null,['nilai_tka'=>90]);$this->classroom($student);
        $program=BasisDataBeasiswa::create(['nama_beasiswa'=>'Program uji terisolasi','jenjang'=>'SMA','is_active'=>true,'deskripsi'=>'prestasi akademik sains']);
        $program->kriteriaKelayakanBeasiswa()->create(['nama_kriteria'=>'TKA','tipe_kriteria'=>'nilai_tka','operator'=>'>=','nilai_kriteria'=>'80','bobot'=>1]);
        return [$student,$program];
    }

    private function explanations(array $candidates): array
    {
        $out=[];foreach($candidates as $candidate)$out[$candidate['beasiswa_id']]=['alasan'=>'Mengikuti pemeriksaan kriteria BE.','persiapan'=>['Periksa dokumen resmi.']];
        return $out;
    }

    public function test_success_returns_saved_pending_draft_and_regenerate_preserves_previous_record(): void
    {
        [$student]=$this->setupStudent();
        $this->mock(GeminiScholarshipExplainer::class,fn($mock)=>$mock->shouldReceive('explain')->twice()->andReturnUsing(fn($c)=>$this->explanations($c)));
        $this->signIn($this->account('administrator'));
        $path="/api/siswa/{$student->id}/rekomendasi-beasiswa/generate";
        $one=$this->postJson($path)->assertCreated()->assertJsonPath('meta.automatic',true)
            ->assertJsonPath('meta.requires_review',false)->assertJsonPath('data.status_review','pending')
            ->assertJsonStructure(['message','meta'=>['generated_at'],'data'=>['id','rekomendasi_beasiswa_detail']]);
        $two=$this->postJson($path)->assertCreated();
        $this->assertNotEquals($one->json('data.id'),$two->json('data.id'));
        $this->assertDatabaseCount('rekomendasi_beasiswa',2);
        $this->assertDatabaseCount('rekomendasi_beasiswa_detail',2);
    }

    public function test_concurrent_generate_is_rejected_before_provider_without_erasing_old_draft(): void
    {
        [$student]=$this->setupStudent();
        $this->mock(GeminiScholarshipExplainer::class,fn($mock)=>$mock->shouldNotReceive('explain'));
        $lock=Cache::lock('scholarship-generate:'.$student->id,150);$this->assertTrue($lock->get());
        $this->signIn($this->account('administrator'));
        try{$this->postJson("/api/siswa/{$student->id}/rekomendasi-beasiswa/generate")->assertStatus(409);}
        finally{$lock->release();}
        $this->assertDatabaseCount('rekomendasi_beasiswa',0);
    }

    public function test_changed_sources_during_ai_call_are_not_saved_and_lock_is_released(): void
    {
        [$student,$program]=$this->setupStudent();
        $this->mock(GeminiScholarshipExplainer::class,fn($mock)=>$mock->shouldReceive('explain')->once()->andReturnUsing(function($c)use($program){$program->update(['deskripsi'=>'Syarat terbaru telah berubah.']);return $this->explanations($c);}));
        $this->signIn($this->account('administrator'));
        $this->postJson("/api/siswa/{$student->id}/rekomendasi-beasiswa/generate")->assertStatus(409);
        $this->assertDatabaseCount('rekomendasi_beasiswa',0);
        $this->assertDatabaseCount('rekomendasi_beasiswa_detail',0);
        $lock=Cache::lock('scholarship-generate:'.$student->id,150);$this->assertTrue($lock->get());$lock->release();
    }

    public function test_provider_failure_preserves_existing_draft_and_does_not_report_success(): void
    {
        [$student]=$this->setupStudent();
        $old=RekomendasiBeasiswa::create(['siswa_id'=>$student->id,'target_jenjang'=>'SMA','tanggal_rekomendasi'=>today(),'status_review'=>'pending']);
        $this->mock(GeminiScholarshipExplainer::class,fn($mock)=>$mock->shouldReceive('explain')->once()->andThrow(new \Symfony\Component\HttpKernel\Exception\HttpException(503,'Layanan rekomendasi belum tersedia.')));
        $this->signIn($this->account('administrator'));
        $this->postJson("/api/siswa/{$student->id}/rekomendasi-beasiswa/generate")->assertStatus(503)->assertJsonMissing(['published'=>false]);
        $this->assertDatabaseCount('rekomendasi_beasiswa',1);
        $this->assertDatabaseHas('rekomendasi_beasiswa',['id'=>$old->id]);
    }

    public function test_empty_catalog_does_not_call_provider_or_create_draft(): void
    {
        [$student,$program]=$this->setupStudent();$program->update(['is_active'=>false]);
        $this->mock(GeminiScholarshipExplainer::class,fn($mock)=>$mock->shouldNotReceive('explain'));
        $this->signIn($this->account('administrator'));
        $this->postJson("/api/siswa/{$student->id}/rekomendasi-beasiswa/generate")->assertUnprocessable();
        $this->assertDatabaseCount('rekomendasi_beasiswa',0);
    }
}

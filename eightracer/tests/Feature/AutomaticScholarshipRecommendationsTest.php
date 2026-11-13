<?php
namespace Tests\Feature;

use App\Models\BasisDataBeasiswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\PrdFixtures;
use Tests\TestCase;

class AutomaticScholarshipRecommendationsTest extends TestCase
{
    use RefreshDatabase, PrdFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        config(['gemini.api_key'=>null,'cache.default'=>'array']);
        Http::preventStrayRequests();
    }

    private function program(string $name='Program Akademik', int $minimum=80, array $extra=[]): BasisDataBeasiswa
    {
        $b=BasisDataBeasiswa::create($extra+['nama_beasiswa'=>$name,'jenjang'=>'SMA','is_active'=>true]);
        $b->kriteriaKelayakanBeasiswa()->create(['nama_kriteria'=>'Nilai TKA','tipe_kriteria'=>'nilai_tka','operator'=>'>=','nilai_kriteria'=>(string)$minimum]);
        return $b;
    }

    public function test_student_receives_current_results_without_any_draft_or_school_approval(): void
    {
        $user=$this->account();$s=$this->student($user,['nilai_tka'=>90]);$this->classroom($s);
        $good=$this->program();$bad=$this->program('Program Nilai Tinggi',95);
        $this->program('Arsip',70,['is_active'=>false]);$this->program('Tutup',70,['tanggal_tutup'=>today()->subDay()]);
        $this->signIn($user);
        $r=$this->getJson('/api/rekomendasi-beasiswa/saya')->assertOk()->assertJsonPath('automatic',true)
            ->assertJsonPath('data.llm_used',false)->assertJsonCount(2,'data.kandidat');
        $items=collect($r->json('data.kandidat'))->keyBy('beasiswa_id');
        $this->assertTrue($items[$good->id]['evaluasi']['memenuhi']);$this->assertFalse($items[$bad->id]['evaluasi']['memenuhi']);
        $r->assertDontSee('source_fingerprint')->assertDontSee($user->email)->assertDontSee($s->nisn);
        $this->assertDatabaseCount('rekomendasi_beasiswa',0);Http::assertNothingSent();
        $s->update(['nilai_tka'=>70]);
        $this->getJson('/api/rekomendasi-beasiswa/saya')->assertOk()->assertJsonPath('data.kandidat.0.evaluasi.memenuhi',false);
    }

    public function test_unknown_data_remains_visible_as_unknown_without_fabricating_eligibility(): void
    {
        $u=$this->account();$s=$this->student($u);$this->classroom($s);$this->program();$this->signIn($u);
        $this->getJson('/api/rekomendasi-beasiswa/saya')->assertOk()->assertJsonPath('data.kandidat.0.evaluasi.lengkap',false)
            ->assertJsonPath('data.kandidat.0.evaluasi.memenuhi',false)->assertJsonPath('data.kandidat.0.evaluasi.checklist.0.status','belum_diketahui');
        $this->assertNull($s->fresh()->nilai_tka);
    }

    public function test_student_identity_is_bound_to_login_and_cannot_be_replaced_by_query(): void
    {
        $u=$this->account();$s=$this->student($u,['nilai_tka'=>50]);$this->classroom($s);
        $other=$this->student($this->account(),['nilai_tka'=>100]);$this->classroom($other);$this->program();
        $this->signIn($u);
        $this->getJson('/api/rekomendasi-beasiswa/saya?siswa_id='.$other->id)->assertUnprocessable();
        $this->getJson('/api/rekomendasi-beasiswa/saya')->assertOk()->assertJsonPath('data.kandidat.0.evaluasi.memenuhi',false);
        $this->signIn($this->account('administrator'));$this->getJson('/api/rekomendasi-beasiswa/saya')->assertForbidden();
    }

    public function test_valid_gemini_explanation_is_cached_and_receives_no_student_identity(): void
    {
        $u=$this->account();$s=$this->student($u,['nama_lengkap'=>'PRIVATE_STUDENT','nilai_tka'=>90]);$this->classroom($s);$b=$this->program();
        config(['gemini.api_key'=>'test-only-key']);
        Http::fake(['generativelanguage.googleapis.com/*'=>function($request)use($s,$u,$b){
            foreach(['PRIVATE_STUDENT',$s->nisn,$u->email] as $private)$this->assertStringNotContainsString($private,$request['input']);
            $json=json_encode(['rekomendasi'=>[['beasiswa_id'=>$b->id,'alasan'=>'Nilai memenuhi kriteria yang tercatat.','persiapan'=>['Siapkan dokumen persyaratan.']]]]);
            return Http::response(['status'=>'completed','steps'=>[['type'=>'model_output','content'=>[['type'=>'text','text'=>$json]]]]]);
        }]);
        $this->signIn($u);
        for($i=0;$i<2;$i++)$this->getJson('/api/rekomendasi-beasiswa/saya')->assertOk()->assertJsonPath('data.llm_used',true)
            ->assertJsonPath('data.kandidat.0.alasan','Nilai memenuhi kriteria yang tercatat.');
        Http::assertSentCount(1);
    }

    public function test_gemini_failure_keeps_rankings_and_grad_school_preparation_available(): void
    {
        $u=$this->account();$s=$this->student($u,['tahun_lulus'=>2025]);$this->program('Kuliah',80,['jenjang'=>'Kuliah']);
        config(['gemini.api_key'=>'test-only-key']);Http::fake(['generativelanguage.googleapis.com/*'=>Http::response(['error'=>'PRIVATE_PROVIDER_ERROR'],429)]);
        $this->signIn($u);
        $this->getJson('/api/rekomendasi-beasiswa/saya')->assertOk()->assertJsonPath('data.target_jenjang','Kuliah')
            ->assertJsonPath('data.llm_used',false)->assertJsonCount(1,'data.kandidat')->assertDontSee('PRIVATE_PROVIDER_ERROR');
    }
}

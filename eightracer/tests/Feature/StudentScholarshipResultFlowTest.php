<?php
namespace Tests\Feature;
use App\Models\BasisDataBeasiswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\PrdFixtures;
use Tests\TestCase;
class StudentScholarshipResultFlowTest extends TestCase {
 use RefreshDatabase, PrdFixtures;
 protected function setUp():void { parent::setUp();config(['gemini.api_key'=>null,'cache.default'=>'array']);Http::preventStrayRequests(); }
 private function fixture():array {
  $u=$this->account();$s=$this->student($u,['nilai_tka'=>90]);$this->classroom($s);
  $b=BasisDataBeasiswa::create(['nama_beasiswa'=>'Program Akademik','jenjang'=>'SMA','is_active'=>true]);
  $b->kriteriaKelayakanBeasiswa()->create(['nama_kriteria'=>'Nilai TKA','tipe_kriteria'=>'nilai_tka','operator'=>'>=','nilai_kriteria'=>'80']);
  $this->signIn($u);return [$u,$s,$b];
 }
 public function test_opening_page_never_calculates_or_saves_results():void {
  $this->fixture();
  for($i=0;$i<2;$i++)$this->getJson('/api/rekomendasi-beasiswa/saya')->assertOk()->assertJsonPath('state','not_started')->assertJsonCount(0,'data.kandidat');
  $this->assertDatabaseCount('student_scholarship_results',0);$this->assertDatabaseCount('rekomendasi_beasiswa',0);Http::assertNothingSent();
 }
 public function test_explicit_request_is_saved_and_read_back_without_school_approval_or_provider_calls():void {
  [$u,$s]=$this->fixture();
  $generated=$this->postJson('/api/rekomendasi-beasiswa/saya/generate')->assertOk()->assertJsonPath('state','ready')->assertJsonCount(1,'data.kandidat')->json();
  $this->getJson('/api/rekomendasi-beasiswa/saya')->assertOk()->assertExactJson($generated);
  $this->assertDatabaseCount('student_scholarship_results',1);$this->assertDatabaseCount('rekomendasi_beasiswa',0);
  $this->assertNotNull(DB::table('student_scholarship_results')->where('siswa_id',$s->id)->value('generated_at'));Http::assertNothingSent();
 }
 public function test_results_are_bound_to_current_student_and_cannot_be_reassigned():void {
  [$u,$s]=$this->fixture();$this->postJson('/api/rekomendasi-beasiswa/saya/generate')->assertOk();
  $other=$this->account();$otherStudent=$this->student($other);$this->classroom($otherStudent);$this->signIn($other);
  $this->getJson('/api/rekomendasi-beasiswa/saya')->assertOk()->assertJsonPath('state','not_started');
  $this->postJson('/api/rekomendasi-beasiswa/saya/generate',['siswa_id'=>$s->id])->assertUnprocessable();
  $this->getJson('/api/rekomendasi-beasiswa/saya?siswa_id='.$s->id)->assertUnprocessable();
  $this->assertDatabaseCount('student_scholarship_results',1);
 }
 public function test_changed_sources_require_an_explicit_update_and_preserve_the_saved_result():void {
  [$u,$s]=$this->fixture();$this->postJson('/api/rekomendasi-beasiswa/saya/generate')->assertOk();
  $s->update(['nilai_tka'=>50]);
  $this->getJson('/api/rekomendasi-beasiswa/saya')->assertOk()->assertJsonPath('state','needs_update')->assertJsonCount(0,'data.kandidat');
  $this->assertDatabaseCount('student_scholarship_results',1);
  $this->postJson('/api/rekomendasi-beasiswa/saya/generate')->assertOk()->assertJsonPath('data.kandidat.0.evaluasi.memenuhi',false);
  $this->assertDatabaseCount('student_scholarship_results',1);Http::assertNothingSent();
 }
 public function test_staff_and_alumni_cannot_generate_a_student_result():void {
  [$u,$s]=$this->fixture();$this->signIn($this->account('administrator'));
  $this->postJson('/api/rekomendasi-beasiswa/saya/generate')->assertForbidden();
  $this->signIn($u);$s->update(['tahun_lulus'=>now()->year-1]);
  $this->postJson('/api/rekomendasi-beasiswa/saya/generate')->assertUnprocessable();
  $this->assertDatabaseCount('student_scholarship_results',0);
 }
}

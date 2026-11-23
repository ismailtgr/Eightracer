<?php
namespace Tests\Feature;
use App\Models\BasisDataBeasiswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\PrdFixtures;
use Tests\TestCase;

class StudentFlowRegressionTest extends TestCase {
 use RefreshDatabase,PrdFixtures;
 protected function setUp():void {parent::setUp();config(['gemini.api_key'=>null,'b4.local_student_preview'=>false]);Http::preventStrayRequests();}
 public function test_generate_without_assessment_returns_information_without_saving_personal_results():void {
  $user=$this->account();$student=$this->student($user);$this->classroom($student);$this->signIn($user);
  BasisDataBeasiswa::create(['nama_beasiswa'=>'Program Umum','jenjang'=>'Kuliah','is_active'=>true]);
  $this->postJson('/api/rekomendasi-beasiswa/saya/generate')->assertOk()
   ->assertJsonPath('state','general_information')->assertJsonPath('data.status','general_information')
   ->assertJsonCount(0,'data.candidates')->assertJsonMissingPath('data.eligibility')->assertJsonMissingPath('generated_at');
  $this->assertDatabaseCount('student_scholarship_results',0);Http::assertNothingSent();
 }
 public function test_alumni_search_finds_records_beyond_the_initial_page_and_keeps_consent():void {
  $this->signIn($this->account());
  $target=$this->admission($this->student(null,['nama_lengkap'=>'Target Lama']));$target->update(['izinkan_tampil_di_buku'=>true]);
  for($i=0;$i<21;$i++)$this->admission($this->student())->update(['izinkan_tampil_di_buku'=>true]);
  $this->admission($this->student(null,['nama_lengkap'=>'Target Rahasia']));
  $this->getJson('/api/jejak-alumni')->assertOk()->assertDontSee('Target Lama');
  $this->getJson('/api/jejak-alumni?search=target&include_filters=1')->assertOk()
   ->assertJsonPath('total',1)->assertJsonPath('data.0.id',$target->id)->assertDontSee('Target Rahasia');
  $url='/api/jejak-alumni?perguruan_tinggi_id='.$target->perguruan_tinggi_id.'&program_studi=Informatika&jalur_masuk=Mandiri';
  $this->getJson($url)->assertOk()->assertJsonPath('total',1)->assertJsonPath('data.0.id',$target->id);
 }
}

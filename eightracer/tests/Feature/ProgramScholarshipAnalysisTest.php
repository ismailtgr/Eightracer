<?php
namespace Tests\Feature;
use App\Models\BasisDataBeasiswa;
use App\Models\RekomendasiBeasiswa;
use App\Services\Beasiswa\GeminiScholarshipExplainer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\PrdFixtures;
use Tests\TestCase;
class ProgramScholarshipAnalysisTest extends TestCase
{
 use RefreshDatabase,PrdFixtures;
 protected function setUp():void {parent::setUp();$this->travelTo(\Carbon\Carbon::parse('2026-11-15'));config(['gemini.api_key'=>null,'cache.default'=>'array']);Http::preventStrayRequests();}
 private function program(string $name='Program Sekolah'):BasisDataBeasiswa {
  $p=BasisDataBeasiswa::create(['nama_beasiswa'=>$name,'jenjang'=>'SMA','is_active'=>true,'deskripsi'=>'Program akademik sekolah','penyelenggara'=>'Sekolah']);
  $p->kriteriaKelayakanBeasiswa()->create(['nama_kriteria'=>'Nilai TKA minimum','tipe_kriteria'=>'nilai_tka','operator'=>'>=','nilai_kriteria'=>'85','bobot'=>1]);return $p;
 }
 public function test_catalog_and_analysis_are_staff_only_and_never_auto_generate():void {
  $p=$this->program();$this->signIn($this->account());
  $this->getJson('/api/rekomendasi-program')->assertForbidden();$this->postJson('/api/rekomendasi-program/'.$p->id.'/analisis',[])->assertForbidden();
  $this->signIn($this->account('administrator'));$this->getJson('/api/rekomendasi-program')->assertOk()->assertJsonPath('data.0.name','Program Sekolah')->assertDontSee('skor_kecocokan');
  $this->assertDatabaseCount('rekomendasi_beasiswa',0);Http::assertNothingSent();
 }
 public function test_batch_only_contains_active_students_uses_cursor_and_keeps_unknown_unknown():void {
  $p=$this->program();$students=[];for($i=0;$i<11;$i++){$s=$this->student(null,['nama_lengkap'=>'Siswa '.$i,'nilai_tka'=>$i===0?null:90]);$this->classroom($s);$students[]=$s;}
  $this->student(null,['nama_lengkap'=>'Alumni Rahasia','tahun_lulus'=>2024]);$this->signIn($this->account('administrator'));
  $r=$this->postJson('/api/rekomendasi-program/'.$p->id.'/analisis',[])->assertOk()->assertJsonCount(10,'data')->assertJsonPath('total',11)->assertJsonPath('next_cursor',$students[9]->id)->assertJsonPath('data.0.preview.data.kandidat.0.evaluasi.lengkap',false)->assertDontSee('nisn')->assertDontSee('Alumni Rahasia');
  $this->postJson('/api/rekomendasi-program/'.$p->id.'/analisis',['after_id'=>$r->json('next_cursor')])->assertOk()->assertJsonCount(1,'data')->assertJsonPath('next_cursor',null)->assertJsonPath('data.0.id',$students[10]->id);
  $this->assertDatabaseCount('rekomendasi_beasiswa',0);Http::assertNothingSent();
 }
 public function test_selected_program_is_evaluated_even_beyond_first_twenty_results():void {
  for($i=0;$i<23;$i++)$p=$this->program('Program '.$i);
  $s=$this->student(null,['nilai_tka'=>90]);$this->classroom($s);$this->signIn($this->account('administrator'));
  $this->postJson('/api/rekomendasi-program/'.$p->id.'/analisis',[])->assertOk()->assertJsonPath('data.0.preview.data.kandidat.0.beasiswa_id',$p->id);
 }
 public function test_optional_note_has_revision_and_does_not_overwrite_scores_or_personal_results():void {
  $p=$this->program();$s=$this->student(null,['nilai_tka'=>90]);$this->classroom($s);$this->signIn($this->account('administrator'));
  $row=$this->postJson('/api/rekomendasi-program/'.$p->id.'/analisis',[])->assertOk()->json('data.0');
  $path='/api/rekomendasi-program/'.$p->id.'/siswa/'.$s->id.'/catatan';
  $input=['fingerprint'=>$row['fingerprint'],'revision'=>0,'reason'=>'Siapkan dokumen sesuai syarat.','timing'=>'Sesuai jadwal resmi'];
  $this->postJson($path,$input+['score'=>20])->assertUnprocessable();
  $this->postJson($path,$input)->assertOk()->assertJsonPath('data.revision',1);
  $this->assertDatabaseHas('rekomendasi_beasiswa_detail',['beasiswa_id'=>$p->id,'skor_kecocokan'=>100,'alasan_rekomendasi'=>'Siapkan dokumen sesuai syarat.']);
  $this->assertDatabaseCount('student_scholarship_results',0);
  $this->postJson($path,$input)->assertConflict();
  $this->postJson($path,[...$input,'revision'=>1,'reason'=>'Periksa dokumen terbaru.'])->assertOk()->assertJsonPath('data.revision',2);
  $s->update(['nilai_tka'=>70]);$this->postJson($path,[...$input,'revision'=>2])->assertConflict();
  $this->assertDatabaseHas('rekomendasi_beasiswa_detail',['beasiswa_id'=>$p->id,'skor_kecocokan'=>100]);
 }
 public function test_ai_explanation_is_optional_cached_and_does_not_save_any_result():void {
  $p=$this->program();$s=$this->student(null,['nilai_tka'=>90]);$this->classroom($s);$this->signIn($this->account('administrator'));
  $this->mock(GeminiScholarshipExplainer::class)->shouldReceive('explain')->once()->andReturn([$p->id=>['alasan'=>'Kriteria yang tercatat terpenuhi.','persiapan'=>['Periksa jadwal resmi.']]]);
  $path='/api/rekomendasi-program/'.$p->id.'/siswa/'.$s->id.'/penjelasan';
  for($i=0;$i<2;$i++)$this->postJson($path,[])->assertOk()->assertJsonPath('data.llmUsed',true);
  $this->assertDatabaseCount('rekomendasi_beasiswa',0);$this->assertDatabaseCount('student_scholarship_results',0);
 }
 public function test_alumni_archives_and_closed_programs_are_not_analysed():void {
  $p=$this->program();$s=$this->student(null,['tahun_lulus'=>2024]);$this->signIn($this->account('administrator'));
  $this->postJson('/api/rekomendasi-program/'.$p->id.'/siswa/'.$s->id.'/penjelasan',[])->assertUnprocessable();
  $p->update(['tanggal_tutup'=>'2026-01-01']);$this->postJson('/api/rekomendasi-program/'.$p->id.'/analisis',[])->assertUnprocessable();
  $p->update(['is_active'=>false]);$this->postJson('/api/rekomendasi-program/'.$p->id.'/analisis',[])->assertNotFound();
 }
}

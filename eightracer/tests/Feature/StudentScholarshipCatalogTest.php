<?php
namespace Tests\Feature;
use App\Models\AlumniProgress;
use App\Models\AlumniProgressBeasiswa;
use App\Models\BasisDataBeasiswa;
use App\Models\SchoolScholarshipObservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\PrdFixtures;
use Tests\TestCase;
class StudentScholarshipCatalogTest extends TestCase {
 use RefreshDatabase,PrdFixtures;
 protected function setUp():void {parent::setUp();config(['gemini.api_key'=>null,'b4.local_student_preview'=>false]);Http::preventStrayRequests();}
 private function program(string $name='Beasiswa TELADAN'):BasisDataBeasiswa {return BasisDataBeasiswa::create(['nama_beasiswa'=>$name,'jenjang'=>'Kuliah','is_active'=>true,'penyelenggara'=>'Penyelenggara Resmi','deskripsi'=>'Informasi program dari sekolah.']);}
 private function source($s,$a,string $name):void {SchoolScholarshipObservation::create(['source_key'=>hash('sha256',uniqid()),'source_file_id'=>'school','source_sha256'=>str_repeat('a',64),'source_sheet'=>'MANDIRI','source_row'=>1,'source_column'=>'I','tahun_lulus'=>2026,'siswa_id'=>$s->id,'study_tracer_id'=>$a->id,'nama_kolom'=>'BEASISWA','nilai_sumber'=>$name,'jenis_keterangan'=>'referensi_beasiswa']);}
 public function test_student_can_browse_general_catalog_without_profile_or_personal_calculation():void {
  $p=$this->program();$closed=$this->program('Arsip');$closed->update(['is_active'=>false]);$this->signIn($this->account());
  $this->getJson('/api/beasiswa-siswa')->assertOk()->assertJsonCount(1,'data')->assertJsonPath('data.0.id',$p->id)->assertDontSee('skor_kecocokan')->assertDontSee('official_policy');
  $this->getJson('/api/beasiswa-siswa/'.$p->id)->assertOk()->assertJsonPath('data.provider','Penyelenggara Resmi')->assertJsonCount(0,'data.recipients.data')->assertDontSee('evaluasi');
  $this->getJson('/api/beasiswa-siswa/'.$closed->id)->assertNotFound();
  $this->assertDatabaseCount('student_scholarship_results',0);$this->assertDatabaseCount('rekomendasi_beasiswa',0);Http::assertNothingSent();
 }
 public function test_recipients_match_program_and_published_acceptance_without_private_data():void {
  $p=$this->program();$s=$this->student(null,['nama_lengkap'=>'Penerima Sebenarnya','tahun_lulus'=>2026]);$a=$this->admission($s);$a->update(['izinkan_tampil_di_buku'=>true]);$this->source($s,$a,'BEASISWA TELADAN');
  $other=$this->student(null,['nama_lengkap'=>'Penerima Program Lain']);$b=$this->admission($other);$b->update(['izinkan_tampil_di_buku'=>true]);$this->source($other,$b,'KIP-K');
  $hidden=$this->student(null,['nama_lengkap'=>'PRIVATE_HIDDEN']);$h=$this->admission($hidden);$this->source($hidden,$h,'Beasiswa TELADAN');
  $this->source($other,$a,'KIP-K');$this->signIn($this->account());
  $this->getJson('/api/beasiswa-siswa/'.$p->id)->assertOk()->assertJsonCount(1,'data.recipients.data')->assertJsonPath('data.recipients.data.0.name','Penerima Sebenarnya')->assertJsonPath('data.recipients.data.0.admissionId',$a->id)->assertDontSee('PRIVATE_HIDDEN')->assertDontSee('Penerima Program Lain')->assertDontSee('PRIVATE_PHONE')->assertDontSee($s->nisn);
 }
 public function test_latest_published_rejection_does_not_turn_into_a_recipient_from_an_old_source():void {
  $p=$this->program('KIP-K');$s=$this->student();$a=$this->admission($s);$a->update(['izinkan_tampil_di_buku'=>true]);$this->source($s,$a,'KIP K');
  $progress=AlumniProgress::create(['siswa_id'=>$s->id,'study_tracer_id'=>$a->id,'tahun_lulus'=>2026,'periode'=>'Ganjil 2026/2027','semester'=>1,'tanggal_update'=>today(),'is_current'=>true,'is_published'=>true,'penerima_kipk'=>false]);
  AlumniProgressBeasiswa::create(['alumni_progress_id'=>$progress->id,'beasiswa_id'=>$p->id,'status_beasiswa'=>'ditolak']);$this->signIn($this->account());
  $this->getJson('/api/beasiswa-siswa/'.$p->id)->assertOk()->assertJsonCount(0,'data.recipients.data');
 }
 public function test_selected_program_general_chat_works_before_any_student_result_exists():void {
  $p=$this->program();$this->signIn($this->account());
  $this->postJson('/api/chat-beasiswa',['message'=>'Apa saja persyaratan beasiswa ini?','beasiswa_id'=>$p->id])->assertOk()->assertJsonPath('data.status','general_information')->assertJsonPath('data.beasiswa_id',$p->id)->assertJsonPath('data.llm_used',false)->assertSee('Penyelenggara Resmi')->assertDontSee('evaluasi');
  $this->postJson('/api/chat-beasiswa',['message'=>'Apakah aku cocok dengan beasiswa ini?','beasiswa_id'=>$p->id])->assertNotFound();
  $this->assertDatabaseCount('student_scholarship_results',0);Http::assertNothingSent();
 }
}

<?php
namespace Tests\Feature;
use App\Models\BasisDataBeasiswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\PrdFixtures;
use Tests\TestCase;
class ActiveScholarshipStudentTest extends TestCase {
 use RefreshDatabase, PrdFixtures;
 public function test_only_current_sma_students_are_returned_without_affecting_general_directory():void {
  $active=$this->student();$this->classroom($active);
  $alumni=$this->student(null,['tahun_lulus'=>now()->year-1]);$this->classroom($alumni);
  $ended=$this->student();$this->classroom($ended);$ended->riwayatKelas()->update(['tanggal_selesai'=>today()->subDay()]);
  $unknown=$this->student();
  $old=$this->student();$this->classroom($old);$old->riwayatKelas()->first()->kelas->update(['tahun_ajaran'=>'2020/2021']);
  $this->signIn($this->account('administrator'));
  $this->getJson('/api/siswa?aktif_rekomendasi=1')->assertOk()->assertJsonPath('data.total',1)->assertJsonPath('data.data.0.id',$active->id);
  $this->getJson('/api/siswa')->assertOk()->assertJsonPath('data.total',5);
 }
 public function test_alumni_cannot_bypass_scope_by_direct_ids_in_preview_save_generate_or_chat():void {
  Http::preventStrayRequests();
  $owner=$this->account();$alumni=$this->student($owner,['tanggal_lulus'=>today()->subMonth(),'tahun_lulus'=>now()->year]);$this->classroom($alumni);
  BasisDataBeasiswa::create(['nama_beasiswa'=>'Program terisolasi','jenjang'=>'Kuliah','is_active'=>true]);
  $this->signIn($this->account('administrator'));
  $this->getJson("/api/siswa/{$alumni->id}/rekomendasi-beasiswa/preview?target_jenjang=Kuliah")->assertUnprocessable();
  foreach(['save','generate'] as $action)$this->postJson("/api/siswa/{$alumni->id}/rekomendasi-beasiswa/$action",['target_jenjang'=>'Kuliah'])->assertUnprocessable();
  $this->signIn($owner);$this->getJson('/api/rekomendasi-beasiswa/saya')->assertUnprocessable();
  $this->postJson('/api/chat-beasiswa',['message'=>'Beasiswa apa yang cocok?'])->assertUnprocessable();
  $this->assertDatabaseCount('rekomendasi_beasiswa',0);Http::assertNothingSent();
 }
}

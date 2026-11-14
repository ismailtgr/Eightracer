<?php
namespace Tests\Feature;
use App\Models\BasisDataBeasiswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PrdFixtures;
use Tests\TestCase;
class ScholarshipReactivationTest extends TestCase {
 use RefreshDatabase, PrdFixtures;
 public function test_reactivation_preserves_official_policy_and_empty_numeric_criteria():void {
  $policy=['manual_requirements'=>[['code'=>'seleksi','label'=>'Seleksi resmi']], 'review_required'=>false];
  $p=BasisDataBeasiswa::forceCreate(['nama_beasiswa'=>'TELADAN terisolasi','jenjang'=>'Kuliah','is_active'=>false,'deskripsi'=>'Ketentuan resmi','official_policy'=>$policy]);
  $this->signIn($this->account('administrator'));
  $url="/api/beasiswa/{$p->id}/activate";
  $this->postJson($url,[])->assertOk()->assertJsonPath('data.is_active',true);
  $this->postJson($url,[])->assertOk();
  $this->assertSame($policy,$p->fresh()->official_policy);
  $this->assertSame('Ketentuan resmi',$p->fresh()->deskripsi);
  $this->assertCount(0,$p->fresh()->kriteriaKelayakanBeasiswa);
  $this->assertDatabaseCount('basis_data_beasiswa',1);
 }
 public function test_only_staff_can_activate_and_payload_cannot_replace_requirements():void {
  $p=BasisDataBeasiswa::create(['nama_beasiswa'=>'Program terisolasi','jenjang'=>'Kuliah','is_active'=>false]);$url="/api/beasiswa/{$p->id}/activate";
  $this->postJson($url)->assertUnauthorized();
  $this->signIn($this->account('siswa'));$this->postJson($url)->assertForbidden();
  $this->signIn($this->account('administrator'));$this->postJson($url,['official_policy'=>[]])->assertUnprocessable();
  $this->assertFalse($p->fresh()->is_active);
 }
}

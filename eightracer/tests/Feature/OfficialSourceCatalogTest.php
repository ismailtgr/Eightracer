<?php
namespace Tests\Feature;
use App\Models\BasisDataBeasiswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PrdFixtures;
use Tests\TestCase;
class OfficialSourceCatalogTest extends TestCase {
 use PrdFixtures, RefreshDatabase;
 private function payload():array{return ['nama_beasiswa'=>'Katalog sumber','jenjang'=>'Kuliah','kriteria'=>[['nama_kriteria'=>'Semester','tipe_kriteria'=>'semester_kuliah','operator'=>'=','nilai_kriteria'=>'4','bobot'=>1]]];}
 public function test_catalog_editor_cannot_forge_official_source_metadata():void {
  $this->signIn($this->account('administrator'));$data=$this->payload();$data['official_policy']=['sources'=>[['url'=>'https://example.test/forged']]];
  $this->postJson('/api/beasiswa',$data)->assertUnprocessable()->assertJsonValidationErrors('official_policy');$this->assertDatabaseCount('basis_data_beasiswa',0);
 }
 public function test_editing_verified_catalog_marks_source_review_required():void {
  $this->signIn($this->account('administrator'));$b=new BasisDataBeasiswa;$b->forceFill(['nama_beasiswa'=>'Original','jenjang'=>'Kuliah','official_policy'=>['version'=>'test','sources'=>[['url'=>'https://example.test/original']]]])->save();
  $this->putJson('/api/beasiswa/'.$b->id,$this->payload())->assertOk()->assertJsonPath('data.official_policy.review_required',true)->assertJsonPath('data.official_policy.sources.0.url','https://example.test/original');
  $this->assertDatabaseCount('basis_data_beasiswa',1);
 }
}

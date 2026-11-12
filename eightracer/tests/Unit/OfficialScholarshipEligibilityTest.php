<?php
namespace Tests\Unit;
use App\Models\BasisDataBeasiswa;
use App\Services\Beasiswa\OfficialScholarshipEligibility;
use Tests\TestCase;
class OfficialScholarshipEligibilityTest extends TestCase {
 private function item(int $id): array {
  return collect(json_decode(file_get_contents(base_path('database/data/scholarships/official-20261112.json')),true)['items'])->firstWhere('id',$id);
 }
 private function runPolicy(int $id,array $profile,bool $stale=false):array {
  $i=$this->item($id);$p=new BasisDataBeasiswa;$policy=$i['official_policy'];if($stale)$policy['review_required']=true;
  $p->forceFill(['official_policy'=>$policy]);return app(OfficialScholarshipEligibility::class)->evaluate($profile,$i['criteria'],$p);
 }
 private function college(array $extra=[]):array {return array_merge(['semester_kuliah'=>4,'ipk'=>3.2,'nama_perguruan_tinggi'=>'UB','strata_pendidikan'=>'S1','status_kuliah'=>'aktif'],$extra);}
 public function test_lotte_paths_are_alternatives_and_financial_evidence_is_not_invented():void {
  $this->assertTrue($this->runPolicy(1,$this->college())['memenuhi']);
  $economic=$this->runPolicy(1,$this->college(['ipk'=>2.8]));$this->assertFalse($economic['memenuhi']);$this->assertFalse($economic['lengkap']);
  $below=$this->runPolicy(1,$this->college(['ipk'=>2.79]));$this->assertFalse($below['memenuhi']);$this->assertTrue($below['lengkap']);
  $this->assertTrue($this->runPolicy(1,$this->college(['strata_pendidikan'=>'D4']))['memenuhi']);
 }
 public function test_verified_campus_degree_status_and_semester_cannot_be_ignored():void {
  foreach([['nama_perguruan_tinggi'=>'ITB'],['strata_pendidikan'=>'S2'],['semester_kuliah'=>5],['status_kuliah'=>'cuti'],['nama_perguruan_tinggi'=>null]] as $change)$this->assertFalse($this->runPolicy(1,$this->college($change))['memenuhi']);
 }
 public function test_teladan_does_not_substitute_tka_or_grades_one_to_five_for_grade_twelve():void {
  $p=$this->college(['semester_kuliah'=>1,'nilai_tka'=>100,'rata_rapor'=>100]);
  $r=$this->runPolicy(2,$p);$grade=collect($r['checklist'])->firstWhere('kriteria','rata_rapor_kelas_12');
  $this->assertSame('belum_diketahui',$grade['status']);$this->assertFalse($r['memenuhi']);
  $r=$this->runPolicy(2,$p+['rata_rapor_kelas_12'=>80]);$this->assertFalse($r['lengkap']);$this->assertFalse($r['memenuhi']);
  $this->assertSame('terpenuhi',collect($r['checklist'])->firstWhere('kriteria','rata_rapor_kelas_12')['status']);
 }
 public function test_unreviewed_catalog_edits_invalidate_official_verification():void {
  $r=$this->runPolicy(1,$this->college(),true);$this->assertFalse($r['lengkap']);$this->assertFalse($r['memenuhi']);
 }
 public function test_grade_twelve_average_requires_both_semesters_five_and_six():void {
  $s=new \App\Models\Siswa;
  foreach(['nilaiRapor','orangTua','riwayatKelas','alumniProgress','rekapPresensi','prestasiMurid','riwayatOrganisasi','catatanKarakter','bantuanSiswa'] as $relation)$s->setRelation($relation,new \Illuminate\Database\Eloquent\Collection);
  $g5=new \App\Models\NilaiRapor(['semester'=>5,'nilai_akhir'=>90]);$s->setRelation('nilaiRapor',new \Illuminate\Database\Eloquent\Collection([$g5]));
  $service=app(\App\Services\School\StudentProfile::class);$this->assertNull($service->build($s)['rata_rapor_kelas_12']);
  $g6=new \App\Models\NilaiRapor(['semester'=>6,'nilai_akhir'=>80]);$s->setRelation('nilaiRapor',new \Illuminate\Database\Eloquent\Collection([$g5,$g6]));
  $this->assertEquals(85,$service->build($s)['rata_rapor_kelas_12']);
 }
 public function test_official_periods_are_closed_at_the_audit_date():void {
  $this->travelTo(\Carbon\Carbon::parse('2026-11-12 12:00:00','Asia/Jakarta'));
  try {foreach([1,2] as $id){$b=new BasisDataBeasiswa;$b->forceFill($this->item($id)['changes']+['is_active'=>true]);$this->assertSame('ditutup',$b->status_pendaftaran);}} finally {$this->travelBack();}
 }
}

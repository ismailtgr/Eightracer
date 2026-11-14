<?php
namespace Tests\Unit;
use App\Services\Beasiswa\ScholarshipContentRanker;
use Tests\TestCase;
class ScholarshipHighlightTest extends TestCase {
 private function c(int $id,bool $eligible=true,bool $complete=true,int $score=100,array $checks=[]):array{return ['beasiswa_id'=>$id,'evaluasi'=>['memenuhi'=>$eligible,'lengkap'=>$complete,'skor'=>$score,'checklist'=>$checks]];}
 public function test_criteria_score_precedes_similarity_and_returns_a_reason():void {
  [$rows]=app(ScholarshipContentRanker::class)->rank([$this->c(1,true,true,100),$this->c(2,true,true,80)],[1=>'matematika sains',2=>'basket atlet olahraga'],'basket atlet olahraga');
  $this->assertSame([1,2],array_column($rows,'beasiswa_id'));$this->assertTrue($rows[0]['evaluasi']['pemeringkatan_ml']['unggulan']);$this->assertSame('Paling Sesuai',$rows[0]['evaluasi']['pemeringkatan_ml']['label_unggulan']);
 }
 public function test_equal_criteria_use_personal_interest_but_unknown_is_exploration_only():void {
  $r=app(ScholarshipContentRanker::class);$docs=[1=>'matematika sains',2=>'basket atlet olahraga'];
  [$rows]=$r->rank([$this->c(1),$this->c(2)],$docs,'basket atlet');$this->assertSame(2,$rows[0]['beasiswa_id']);$this->assertSame('Paling Sesuai',$rows[0]['evaluasi']['pemeringkatan_ml']['label_unggulan']);
  [$rows]=$r->rank([$this->c(1,false,false,0),$this->c(2,false,false,0)],$docs,'basket atlet');$this->assertFalse($rows[0]['evaluasi']['memenuhi']);$this->assertSame('Prioritas Dipelajari',$rows[0]['evaluasi']['pemeringkatan_ml']['label_unggulan']);
 }
 public function test_ties_do_not_invent_a_winner_and_failed_criteria_never_get_star():void {
  $r=app(ScholarshipContentRanker::class);
  [$rows]=$r->rank([$this->c(1),$this->c(2)],[1=>'basket atlet',2=>'basket atlet'],'basket atlet');
  $this->assertFalse($rows[0]['evaluasi']['pemeringkatan_ml']['unggulan']);
  [$rows]=$r->rank([$this->c(1,false,true,99),$this->c(2,false,false,0)],[1=>'basket atlet',2=>'sains matematika'],'basket atlet');
  $this->assertSame(2,$rows[0]['beasiswa_id']);foreach($rows as $row)$this->assertFalse($row['evaluasi']['pemeringkatan_ml']['unggulan']);
 }
}

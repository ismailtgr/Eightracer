<?php
namespace Tests\Unit;
use App\Models\BasisDataBeasiswa;
use App\Services\Beasiswa\{OfficialScholarshipEligibility,ScholarshipContentRanker};
use Tests\TestCase;
class VerifiedScholarshipExplorationTest extends TestCase
{
    public function test_source_pack_identifies_actual_providers_and_never_invents_numeric_thresholds(): void
    {
        $pack=require database_path('data/scholarships/verified-20261114.php');
        $this->assertCount(5,$pack['items']);
        foreach($pack['items'] as $item){
            $this->assertNotEmpty($item['sources']);
            foreach($item['sources'] as $source)$this->assertStringStartsWith('https://',$source['url']);
            $p=new BasisDataBeasiswa;
            $p->forceFill(['official_policy'=>$item]);
            $result=app(OfficialScholarshipEligibility::class)->evaluate([],[],$p);
            $this->assertFalse($result['memenuhi']);
            $this->assertFalse($result['lengkap']);
            $this->assertSame(0.0,(float)$result['cakupan_data']);
        }
        $teladan=collect($pack['items'])->firstWhere('id',5);
        $this->assertSame('UIN Maulana Malik Ibrahim Malang',$teladan['provider']);
    }
    public function test_exploration_orders_incomplete_profiles_without_qualifying_them(): void
    {
        $candidate=fn($id,$complete=false,$eligible=false)=>['beasiswa_id'=>$id,'evaluasi'=>['memenuhi'=>$eligible,'lengkap'=>$complete,'skor'=>$eligible?100:0,'checklist'=>[['status'=>$eligible?'terpenuhi':($complete?'belum_terpenuhi':'belum_diketahui')]]]];
        [$ranked,$meta]=app(ScholarshipContentRanker::class)->rank([$candidate(1),$candidate(2),$candidate(3,true,true),$candidate(4,true)],
            [1=>'keperawatan perawat kesehatan rumah sakit',2=>'basket atlet olahraga kejuaraan',3=>'sains fisika',4=>'basket olahraga'],'basket atlet');
        $this->assertSame([3,2,1,4],array_column($ranked,'beasiswa_id'));
        $this->assertSame('exploration_only',$meta['alasan_status']);
        $this->assertFalse($ranked[1]['evaluasi']['memenuhi']);
        $this->assertFalse($ranked[1]['evaluasi']['lengkap']);
        $this->assertSame(0,$ranked[1]['evaluasi']['skor']);
        $this->assertSame('belum_diketahui',$ranked[1]['evaluasi']['checklist'][0]['status']);
        $this->assertNull($ranked[3]['evaluasi']['pemeringkatan_ml']['skor_kemiripan']);
    }
    public function test_no_interest_or_no_overlap_does_not_claim_personalization(): void
    {
        $items=array_map(fn($id)=>['beasiswa_id'=>$id,'evaluasi'=>['memenuhi'=>false,'lengkap'=>false,'skor'=>0,'checklist'=>[['status'=>'belum_diketahui']]]],[1,2]);
        foreach(['','kosakatatidakdikenal'] as $query){
            [, $meta]=app(ScholarshipContentRanker::class)->rank($items,[1=>'basket atlet',2=>'keperawatan kesehatan'],$query);
            $this->assertSame('fallback',$meta['status']);
        }
    }
}

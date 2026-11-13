<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Models\BasisDataBeasiswa;
use App\Services\Beasiswa\{TfidfScholarshipModel,ScholarshipContentRanker,OfficialScholarshipEligibility,GeminiScholarshipExplainer};

class EvaluateScholarshipEvidence extends Command
{
    protected $signature='scholarships:evaluate {--live-ai : Make one Gemini request using non-personal test cases}';
    protected $description='Evaluate reference retrieval and safety using an explicit, small diagnostic benchmark';
    public function handle(): int
    {
        $pack=require database_path('data/scholarships/verified-20261114.php');
        $docs=array_column($pack['items'],'description','id');
        // Labels are fixed independently of model output. No student or acceptance outcome is used.
        $cases=[
            ['query'=>'atlet basket olahraga kejuaraan sertifikat','relevant'=>[7]],
            ['query'=>'keterbatasan ekonomi pendapatan orang tua SKTM DTSEN','relevant'=>[4]],
            ['query'=>'keperawatan profesi ners resiprokasi ikatan dinas','relevant'=>[8]],
            ['query'=>'pembebasan IPI biaya awal institusi','relevant'=>[6]],
            ['query'=>'tahfidz Al-Quran qiraatul kutub Alfiyah','relevant'=>[5]],
            ['query'=>'bantuan biaya hidup kondisi finansial keluarga','relevant'=>[4]],
            ['query'=>'rumah sakit perawat pendidikan kesehatan','relevant'=>[8]],
            ['query'=>'juara olahraga basket provinsi','relevant'=>[7]],
            ['query'=>'sertifikat bahasa Inggris bebas biaya IPI','relevant'=>[6]],
            ['query'=>'hafalan hadist ahkam seni mahad UIN','relevant'=>[5]],
        ];
        $model=app(TfidfScholarshipModel::class);$trained=$model->fit($docs);
        $rows=[];$hits=$rr=$p3=$baseHits=$baseRr=$coverage=0;
        foreach($cases as $case){
            $scores=$model->similarities($trained,$case['query']);
            uksort($scores,fn($a,$b)=>($scores[$b]<=>$scores[$a])?:($a<=>$b));
            $ids=array_keys($scores);$rank=null;$baseRank=null;$baseline=array_keys($docs);sort($baseline);
            foreach($ids as $i=>$id)if(in_array($id,$case['relevant'],true)){$rank=$i+1;break;}
            foreach($baseline as $i=>$id)if(in_array($id,$case['relevant'],true)){$baseRank=$i+1;break;}
            $hits+=(int)($rank===1);$rr+=$rank?1/$rank:0;$baseHits+=(int)($baseRank===1);$baseRr+=$baseRank?1/$baseRank:0;
            $p3+=count(array_intersect(array_slice($ids,0,3),$case['relevant']))/3;
            $coverage+=(int)($scores!==[] && max($scores)>0);
            $rows[]=['query'=>$case['query'],'expected'=>$case['relevant'],'ranking'=>$ids,'relevant_rank'=>$rank];
        }
        $guardChecks=$guardPassed=0;$aiInput=[];
        foreach($pack['items'] as $item){
            $program=new BasisDataBeasiswa;$program->forceFill(['official_policy'=>$item]);
            foreach([[],['nama_perguruan_tinggi'=>$item['provider']],['nama_perguruan_tinggi'=>'Kampus tidak sesuai']] as $profile){
                $e=app(OfficialScholarshipEligibility::class)->evaluate($profile,[],$program);
                $guardChecks++;$guardPassed+=(int)(!$e['memenuhi'] && !$e['lengkap']);
            }
            if(in_array($item['id'],[4,6,8],true))$aiInput[]=['beasiswa_id'=>$item['id'],'beasiswa'=>['nama_beasiswa'=>$item['name']],'evaluasi'=>app(OfficialScholarshipEligibility::class)->evaluate([],[],$program)];
        }
        $ai=['status'=>'not_run','reason'=>'Use --live-ai for one real provider request. No AI accuracy is inferred from tests.'];
        if($this->option('live-ai')){
            try{
                $answers=app(GeminiScholarshipExplainer::class)->explain($aiInput,30);
                $checks=[];
                foreach($answers as $id=>$answer){
                    $text=mb_strtolower($answer['alasan'].' '.implode(' ',$answer['persiapan']));
                    $checks[]=['id'=>$id,'uncertainty_disclosed'=>(bool)preg_match('/belum|perlu|verifikasi|lengkap|tidak dapat|tidak bisa/u',$text),
                        'no_admission_guarantee'=>!preg_match('/(pasti|dijamin)\s+(lolos|diterima|mendapat)/u',$text)];
                }
                $passed=count(array_filter($checks,fn($c)=>$c['uncertainty_disclosed']&&$c['no_admission_guarantee']));
                $ai=['status'=>'completed','request_count'=>1,'cases'=>count($checks),'passed_heuristic_checks'=>$passed,'checks'=>$checks,
                    'limitations'=>'Small, rule-based diagnostic; not a semantic factual-accuracy measurement.','responses'=>$answers];
            }catch(\Throwable){$ai=['status'=>'unavailable','request_count'=>1,'accuracy'=>null,'reason'=>'Provider did not return a valid response; no quality score assigned.'];}
        }
        $n=count($cases);
        $report=['benchmark_version'=>'reference-diagnostic-20261114-v1','source_version'=>$pack['version'],
            'scope'=>'10 manually authored text-relevance cases against 5 source summaries, including one archived reference for offline testing only. No held-out population data; not admission prediction accuracy.',
            'ml'=>['cases'=>$n,'documents'=>count($docs),'top1'=>round($hits/$n,4),'mrr'=>round($rr/$n,4),'precision_at_3'=>round($p3/$n,4),'coverage'=>round($coverage/$n,4),
                'baseline_id_order'=>['top1'=>round($baseHits/$n,4),'mrr'=>round($baseRr/$n,4)],'cases_detail'=>$rows],
            'eligibility_safety'=>['cases'=>$guardChecks,'passed'=>$guardPassed,'false_eligible'=>$guardChecks-$guardPassed],
            'acceptance_prediction_accuracy'=>null,'ai'=>$ai];
        $directory=storage_path('app/private/scholarship-evaluation');
        if(!is_dir($directory))mkdir($directory,0700,true);
        $json=json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        file_put_contents($directory.'/latest.json',$json,LOCK_EX);$this->line($json);
        return self::SUCCESS;
    }
}

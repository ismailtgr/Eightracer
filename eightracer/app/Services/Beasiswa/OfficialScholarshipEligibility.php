<?php
namespace App\Services\Beasiswa;
use App\Models\BasisDataBeasiswa;
/** Provider requirements remain distinct from text relevance. Unknown evidence never passes. */
class OfficialScholarshipEligibility {
 public function __construct(private EligibilityEvaluator $base) {}
 public function evaluate(array $profile, array $criteria, BasisDataBeasiswa $program): array {
  $result=$this->base->evaluate($profile,$criteria);$policy=$program->official_policy;
  if (!$policy) return $result;
  $checks=$result['checklist'];
  if (!empty($policy['review_required'])) $checks[]=['kriteria'=>'verifikasi_sumber','operator'=>'verifikasi','syarat'=>'Katalog berubah setelah verifikasi; cocokkan kembali dengan sumber resmi','status'=>'belum_diketahui'];
  foreach($policy['scope_checks'] ?? [] as $rule) {
   $field=$rule['field'];$allowed=['nama_perguruan_tinggi','strata_pendidikan','status_kuliah'];
   $actual=in_array($field,$allowed,true)?($profile[$field]??null):null;
   $normalize=fn($v)=>preg_replace('/[^\p{L}\p{N}]+/u','',mb_strtolower(trim((string)$v)));
   $known=$actual!==null && trim((string)$actual)!=='';
   $pass=$known && in_array($normalize($actual),array_map($normalize,$rule['values']),true);
   $checks[]=['kriteria'=>$field,'operator'=>'in','syarat'=>implode(', ',$rule['values']),'status'=>!$known?'belum_diketahui':($pass?'terpenuhi':'belum_terpenuhi')];
  }
  foreach($policy['manual_requirements'] ?? [] as $rule) $checks[]=['kriteria'=>$rule['code'],'operator'=>'verifikasi','syarat'=>$rule['label'],'status'=>'belum_diketahui'];
  if(!empty($policy['alternative_paths'])) {
   $paths=[];$states=[];
   foreach($policy['alternative_paths'] as $path) {
    $p=$this->base->evaluate($profile,$path['criteria']);
    $failed=in_array('belum_terpenuhi',array_column($p['checklist'],'status'),true);
    $state=$failed?'belum_terpenuhi':($p['lengkap'] && empty($path['manual_requirements']) && $p['memenuhi']?'terpenuhi':'belum_diketahui');
    $paths[]=['label'=>$path['label'],'status'=>$state,'checklist'=>$p['checklist'],'dokumen_perlu_diperiksa'=>$path['manual_requirements']??[]];$states[]=$state;
   }
   $status=in_array('terpenuhi',$states,true)?'terpenuhi':(in_array('belum_diketahui',$states,true)?'belum_diketahui':'belum_terpenuhi');
   $checks[]=['kriteria'=>'jalur_pendaftaran','operator'=>'salah_satu','syarat'=>implode(' atau ',array_column($paths,'label')),'status'=>$status];$result['jalur_resmi']=$paths;
  }
  $states=array_column($checks,'status');$result['checklist']=$checks;
  $result['lengkap']=count($checks)>0 && !in_array('belum_diketahui',$states,true);
  $result['memenuhi']=count($checks)>0 && count(array_filter($states,fn($v)=>$v!=='terpenuhi'))===0;
  $result['cakupan_data']=count($checks)>0 ? round(100*count(array_filter($states,fn($v)=>$v!=='belum_diketahui'))/count($checks),2) : 0;
  $result['jumlah_kriteria_belum_diketahui']=count(array_filter($states,fn($v)=>$v==='belum_diketahui'));
  $result['sumber_kriteria']=$policy['sources']??[];$result['kriteria_diverifikasi_pada']=$policy['verified_at']??null;
  return $result;
 }
}

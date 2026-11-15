<?php
namespace App\Services\Beasiswa;
use App\Models\BasisDataBeasiswa;
use App\Models\StudyTracer;
use App\Models\AlumniProgress;
use App\Models\SchoolScholarshipObservation;
use App\Services\School\LocalExampleRecords;
use App\Services\School\SchoolScholarshipAnalytics;
class StudentScholarshipCatalog {
 public function program(BasisDataBeasiswa $program, bool $detail=false, bool $localPreview=false):array {
  $url=$program->link_pendaftaran;
  $parts=is_string($url)?parse_url($url):false;
  if(!$parts||!in_array($parts['scheme']??'', ['http','https'],true)||isset($parts['user'])||isset($parts['pass']))$url=null;
  $data=['id'=>$program->id,'name'=>$program->nama_beasiswa,'provider'=>$program->penyelenggara,
   'description'=>filled($program->deskripsi)?strip_tags($program->deskripsi):null,'level'=>$program->jenjang,
   'opens'=>$program->tanggal_buka?->toDateString(),'closes'=>$program->tanggal_tutup?->toDateString(),
   'period'=>$program->periode_apply,'duration'=>$program->durasi===null?null:(float)$program->durasi,
   'durationUnit'=>$program->satuan_durasi,'registrationUrl'=>$url];
  if($detail) {
   $data['criteria']=$program->kriteriaKelayakanBeasiswa->map(fn($c)=>['name'=>$c->nama_kriteria,'operator'=>$c->operator,'required'=>$c->nilai_kriteria])->values()->all();
   $data['recipients']=$this->recipients($program,$localPreview);
  }
  return $data;
 }
 private function key(string $name):string {
  $token=preg_replace('/[\s\/-]+/u','',mb_strtoupper(trim($name)));
  return in_array($token,['KIP','KIPK','KIPKIPK'],true)?'kip':mb_strtolower(trim(preg_replace('/\s+/u',' ',$name)));
 }
 private function recipients(BasisDataBeasiswa $program,bool $localPreview):array {
  $admissions=StudyTracer::where('status_penerimaan','diterima')->when(!$localPreview,fn($q)=>$q->where('izinkan_tampil_di_buku',true))
   ->whereHas('siswa',fn($q)=>LocalExampleRecords::exclude($q))
   ->with(['siswa:id,nama_lengkap,tahun_lulus','perguruanTinggi:id,nama_pt'])
   ->select(['id','siswa_id','perguruan_tinggi_id','program_studi'])->limit(5001)->get()->keyBy('id');
  if($admissions->count()>5000)return ['data'=>[],'total'=>0];
  $key=$this->key($program->nama_beasiswa);$status=[];
  $progress=AlumniProgress::whereIn('study_tracer_id',$admissions->keys())->where('is_current',true)->when(!$localPreview,fn($q)=>$q->where('is_published',true))
   ->with(['alumniProgressBeasiswa'=>fn($q)=>$q->orderByDesc('id')])->orderByRaw('tanggal_update DESC NULLS LAST')->orderByDesc('id')->get();
  foreach($progress as $p) {
   $admission=$admissions->get($p->study_tracer_id);if(!$admission||(int)$p->siswa_id!==(int)$admission->siswa_id||isset($status[$admission->id]))continue;
   $award=$p->alumniProgressBeasiswa->first(fn($a)=>(int)$a->beasiswa_id===(int)$program->id||$this->key($a->beasiswa_snapshot['nama_beasiswa']??'')===$key);
   if($award)$status[$admission->id]=['accepted'=>$award->status_beasiswa==='diterima','evidence'=>'confirmed_award'];
   elseif($key==='kip'&&$p->penerima_kipk!==null)$status[$admission->id]=['accepted'=>(bool)$p->penerima_kipk,'evidence'=>'confirmed_award'];
  }
  $sources=SchoolScholarshipObservation::whereIn('study_tracer_id',$admissions->keys())->get();
  foreach($sources as $source) {
   $admission=$admissions->get($source->study_tracer_id);
   if(!$admission||(int)$source->siswa_id!==(int)$admission->siswa_id||isset($status[$admission->id]))continue;
   $name=SchoolScholarshipAnalytics::sourceProgram($source);
   if(!$name&&preg_match('/^BEBAS\s+IPI$/iu',trim($source->nilai_sumber)))$name='Bebas IPI';
   if($name&&$this->key($name)===$key)$status[$admission->id]=['accepted'=>true,'evidence'=>'school_record'];
  }
  $rows=$admissions->filter(fn($a)=>$status[$a->id]['accepted']??false)->unique('siswa_id')->map(fn($a)=>[
   'admissionId'=>$a->id,'name'=>$a->siswa->nama_lengkap,'university'=>$a->perguruanTinggi?->nama_pt,
   'program'=>$a->program_studi,'cohort'=>$a->siswa->tahun_lulus===null?null:(string)$a->siswa->tahun_lulus,'evidence'=>$status[$a->id]['evidence']
  ])->sortBy(fn($a)=>mb_strtolower($a['name']))->values();
  return ['data'=>$rows->take(12)->all(),'total'=>$rows->count()];
 }
}

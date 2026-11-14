<?php
namespace App\Services\Beasiswa;
use App\Models\Siswa;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Explicit student requests, separate from optional staff notes and from chat exploration. */
class StudentScholarshipResults {
 public function __construct(private AutomaticScholarshipRecommendations $automatic, private ScholarshipRecommendationService $recommendations) {}
 private function emptyResult(string $state='not_started', ?string $date=null): array {
  return ['automatic'=>true,'state'=>$state,'generated_at'=>$date,'data'=>['target_jenjang'=>'Kuliah','kandidat'=>[],'llm_used'=>false]];
 }
 public function read(Siswa $student): array {
  app(ActiveScholarshipStudents::class)->assertActive($student);
  if(!Schema::hasTable('student_scholarship_results'))return $this->emptyResult();
  $row=DB::table('student_scholarship_results')->where('siswa_id',$student->id)->first();
  if(!$row)return $this->emptyResult();
  // Fingerprint checks use source data only: GET never runs ranking or calls Gemini.
  [,,$fingerprint]=$this->recommendations->sources($student,$row->requested_target);
  if(!hash_equals($row->source_fingerprint,$fingerprint))return $this->emptyResult('needs_update',$row->generated_at);
  return json_decode($row->result,true,512,JSON_THROW_ON_ERROR)+['state'=>'ready','generated_at'=>$row->generated_at];
 }
 public function generate(Siswa $student, ?string $target=null): array {
  app(ActiveScholarshipStudents::class)->assertActive($student);
  if(!Schema::hasTable('student_scholarship_results'))throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->json(['message'=>'Layanan rekomendasi sedang disiapkan. Silakan coba beberapa saat lagi.'],503));
  $lock=Cache::lock('student-scholarship-request:'.$student->id,150);
  abort_unless($lock->get(),409,'Rekomendasi siswa ini sedang diproses. Tunggu hasil sebelum mencoba lagi.');
  try {
   [,,$before]=$this->recommendations->sources($student,$target);
   $payload=$this->automatic->forStudent($student,$target);
   [,,$after]=$this->recommendations->sources($student->fresh(),$target);
   abort_unless(hash_equals($before,$after),409,'Data sumber berubah. Hitung ulang rekomendasi.');
   $date=now()->toIso8601String();
   DB::table('student_scholarship_results')->upsert([
    ['siswa_id'=>$student->id,'source_fingerprint'=>$after,'requested_target'=>$target,
     'result'=>json_encode($payload,JSON_THROW_ON_ERROR),'generated_at'=>$date]
   ],['siswa_id'],['source_fingerprint','requested_target','result','generated_at']);
   return $payload+['state'=>'ready','generated_at'=>$date];
  } finally { $lock->release(); }
 }
}

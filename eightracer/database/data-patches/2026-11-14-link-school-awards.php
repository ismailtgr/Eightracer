<?php
/** Default: read-only validation. --apply requires a new --backup=<private file>. */
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\{AlumniProgress,AlumniProgressBeasiswa,BasisDataBeasiswa,SchoolScholarshipObservation,StudyTracer};
use App\Services\School\{AlumniScholarshipEvidence,SchoolScholarshipAnalytics};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$apply = in_array('--apply', $argv, true); $backup = null;
foreach ($argv as $arg) if (str_starts_with($arg, '--backup=')) $backup = substr($arg, 9);
$batch = 'school-source-awards-20261114-v1';
$definitions = [
 302 => ['student'=>422,'admission'=>308,'campus'=>2,'name'=>'PRESTASI BASKET','provider'=>'Universitas Negeri Malang',
 'description'=>'Catatan beasiswa pada data sekolah untuk penerimaan S1 Psikologi Universitas Negeri Malang melalui jalur Mandiri. Sumber mencantumkan PRESTASI BASKET pada kolom BEASISWA. Nama resmi program, tingkat kejuaraan, manfaat, nominal, dan persyaratan seleksi belum tercantum; prestasi olahraga bukan jaminan penerimaan beasiswa.'],
 343 => ['student'=>461,'admission'=>null,'campus'=>41,'name'=>'Beasiswa dengan ikatan dinas Universitas Pelita Harapan','provider'=>'Universitas Pelita Harapan',
 'description'=>'Catatan beasiswa dengan ikatan dinas Universitas Pelita Harapan pada data sekolah, terkait Faculty of Nursing, Profesi Ners. Sumber tidak mencantumkan nilai minimal, nominal, durasi pendanaan, ketentuan ikatan dinas, atau jadwal seleksi. Ketentuan tersebut harus diperiksa pada dokumen penyelenggara sebelum penilaian kelayakan.'],
];
DB::beginTransaction();
try {
 if ($apply) DB::select('select pg_advisory_xact_lock(?)',[811202614]);
 else DB::statement('SET TRANSACTION READ ONLY');
 $sources = SchoolScholarshipObservation::whereIn('id',array_keys($definitions))->get()->keyBy('id');
 if ($sources->count() !== 2) throw new RuntimeException('Expected two original school observations.');
 foreach ($definitions as $id=>$d) {
  $o=$sources[$id]; $campus=DB::table('perguruan_tinggi')->find($d['campus']);
  if ((int)$o->siswa_id !== $d['student'] || $o->nilai_sumber !== $d['name'] || (int)$o->tahun_lulus !== 2025
    || $o->source_file_id !== '1CPKUio-wXx0jLTn8Nj4ibsM3kOsyNC10'
    || !$campus || $campus->nama_pt !== $o->kampus_sumber) throw new RuntimeException('Source identity changed; review required.');
 }
 $before=app(SchoolScholarshipAnalytics::class)->aggregate(Request::create('/','GET'))['summary'];
 $existing=AlumniProgressBeasiswa::where('beasiswa_snapshot->batch',$batch)->count();
 if ($existing===2) { echo json_encode(['status'=>'already_applied','summary'=>$before],JSON_PRETTY_PRINT).PHP_EOL; DB::rollBack(); exit(0); }
 if ($existing!==0) throw new RuntimeException('Partial batch detected.');
 if (!$apply) { echo json_encode(['status'=>'validated_read_only','new_admissions'=>1,'new_catalog_entries'=>2,'new_linked_awards'=>2,'before'=>$before],JSON_PRETTY_PRINT).PHP_EOL; DB::rollBack(); exit(0); }
 if (!$backup || !is_dir(dirname($backup)) || file_exists($backup)) throw new RuntimeException('A new private backup file is required.');
 $oldProgress=AlumniProgress::whereIn('siswa_id',[422,461])->get();
 $backupData=['batch'=>$batch,'sources'=>$sources->values()->toArray(),
  'admissions'=>StudyTracer::whereIn('siswa_id',[422,461])->get()->toArray(),'progress'=>$oldProgress->toArray(),
  'awards'=>AlumniProgressBeasiswa::whereIn('alumni_progress_id',$oldProgress->pluck('id'))->get()->toArray(),
  'catalog'=>BasisDataBeasiswa::all()->toArray(),'summary'=>$before];
 if (file_put_contents($backup,json_encode($backupData,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),LOCK_EX)===false) throw new RuntimeException('Backup failed.');
 $result=[];
 foreach ($definitions as $sourceId=>$d) {
  $o=$sources[$sourceId];
  if ($d['admission']) {
   $t=StudyTracer::findOrFail($d['admission']);
   if ((int)$t->siswa_id!==$d['student'] || (int)$t->perguruan_tinggi_id!==$d['campus'] || $t->status_penerimaan!=='diterima') throw new RuntimeException('Admission mismatch.');
  } else {
   if (StudyTracer::where('siswa_id',$d['student'])->exists()) throw new RuntimeException('Admission exists; review instead of duplicating.');
   // Keep the original program wording; Profesi is explicitly written, not an inferred S1 qualification.
   $t=StudyTracer::create(['siswa_id'=>$d['student'],'perguruan_tinggi_id'=>$d['campus'],
    'strata_pendidikan'=>'Profesi','program_studi'=>$o->prodi_sumber,'jalur_masuk'=>'Swasta','status_penerimaan'=>'diterima']);
  }
  if ($o->study_tracer_id && (int)$o->study_tracer_id!==(int)$t->id) throw new RuntimeException('Observation linked elsewhere.');
  $o->study_tracer_id=$t->id; $o->save();
  if (BasisDataBeasiswa::whereRaw('LOWER(TRIM(nama_beasiswa)) = ?',[mb_strtolower($d['name'])])->exists()) throw new RuntimeException('Catalog exists; review required.');
  $b=new BasisDataBeasiswa(['nama_beasiswa'=>$d['name'],'penyelenggara'=>$d['provider'],
   'deskripsi'=>$d['description'],'jenjang'=>'Kuliah','is_active'=>true]);
  $b->official_policy=['version'=>$batch,'verified_at'=>null,'school_source'=>'Catatan sekolah: '.$o->source_sheet.'!'.$o->source_column.$o->source_row,
   'sources'=>[['url'=>'https://docs.google.com/spreadsheets/d/'.$o->source_file_id.'/edit','authority'=>'Data sekolah SMA 8']],
   'manual_requirements'=>[['code'=>'verifikasi_ketentuan_program','label'=>'Persyaratan resmi, manfaat, dan jadwal program perlu diverifikasi; catatan penerima tidak menetapkan kelayakan siswa lain.']],
   'scope_checks'=>[['field'=>'nama_perguruan_tinggi','values'=>[$d['provider']]]],
   'aid_type'=>'beasiswa','source_observation_id'=>$sourceId,'registration_dates_unverified'=>true];
  $b->save();
  $p=AlumniProgress::where('siswa_id',$d['student'])->where('study_tracer_id',$t->id)->where('is_current',true)->orderByDesc('tanggal_update')->orderByDesc('id')->first();
  if (!$p) $p=AlumniProgress::create(['siswa_id'=>$d['student'],'study_tracer_id'=>$t->id,'tahun_lulus'=>2025,'tanggal_update'=>'2026-11-14']);
  AlumniProgressBeasiswa::create(['alumni_progress_id'=>$p->id,'beasiswa_id'=>$b->id,'status_beasiswa'=>'diterima',
   'beasiswa_snapshot'=>['nama_beasiswa'=>$b->nama_beasiswa,'penyelenggara'=>$b->penyelenggara,'jenjang'=>'Kuliah',
    'source_observation_id'=>$sourceId,'batch'=>$batch,'tanggal_penetapan'=>null,
    'verification'=>'Penyelarasan penerima dari kolom BEASISWA sekolah atas instruksi pemilik proyek 2026-11-14']]);
  $evidence=app(AlumniScholarshipEvidence::class)->summarize($t);
  if (!collect($evidence['beasiswa'])->contains(fn($v)=>(int)$v['beasiswa_id']===(int)$b->id && $v['status']==='diterima')) throw new RuntimeException('Detail evidence verification failed.');
  $result[]=['source_id'=>$sourceId,'student_id'=>$d['student'],'admission_id'=>$t->id,'catalog_id'=>$b->id,'progress_id'=>$p->id];
 }
 $after=app(SchoolScholarshipAnalytics::class)->aggregate(Request::create('/','GET'))['summary'];
 foreach (['kip','scholarship_non_kip','all_aid','alumni'] as $key) if ($before[$key]!==$after[$key]) throw new RuntimeException('Recipient count changed unexpectedly.');
 if ($after['admissions']!==$before['admissions']+1 || $after['admitted_alumni']!==$before['admitted_alumni']+1) throw new RuntimeException('Admission counts unexpected.');
 DB::commit(); echo json_encode(['status'=>'applied','links'=>$result,'summary'=>$after],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
} catch (Throwable $e) { DB::rollBack(); throw $e; }

<?php
declare(strict_types=1);
// Explicit, backed-up update of two existing catalog records; never changes school profiles/history.
require dirname(__DIR__).'/vendor/autoload.php';$root=dirname(__DIR__);$app=require $root.'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;use App\Models\BasisDataBeasiswa;use App\Services\School\Criteria;
if(($argv[1]??'')!=='--apply')throw new RuntimeException('Explicit --apply required');
if(DB::connection()->getDriverName()!=='pgsql'||!str_contains((string)config('database.connections.pgsql.username'),'hitxegwqiezzomhibpwf'))throw new RuntimeException('Unexpected destination');
$manifest=json_decode(file_get_contents($root.'/database/data/scholarships/official-20261112.json'),true,512,JSON_THROW_ON_ERROR);
$backupDir=$root.'/.migration-private';if(!is_dir($backupDir))mkdir($backupDir,0700,true);
DB::beginTransaction();
try{
 $before=[];$summary=[];
 foreach($manifest['items'] as $item){
  $b=BasisDataBeasiswa::with('kriteriaKelayakanBeasiswa')->lockForUpdate()->findOrFail($item['id']);
  if($b->nama_beasiswa!==$item['expected_name'])throw new RuntimeException('Catalog identity changed');
  if(($b->official_policy['version']??null)===$manifest['version']){$summary[]=['id'=>$b->id,'status'=>'already_applied'];continue;}
  $before[]=$b->toArray();Criteria::validate($item['criteria']);
  $b->forceFill($item['changes']+['official_policy'=>$item['official_policy']])->save();
  $existing=$b->kriteriaKelayakanBeasiswa()->orderBy('id')->get();
  foreach($item['criteria'] as $i=>$c){if(isset($existing[$i]))$existing[$i]->update($c);else $b->kriteriaKelayakanBeasiswa()->create($c);}
  foreach($existing->slice(count($item['criteria'])) as $obsolete)$obsolete->delete();
  $summary[]=['id'=>$b->id,'status'=>'updated','registration_status'=>$b->fresh()->status_pendaftaran,'numeric_criteria'=>count($item['criteria'])];
 }
 if($before)file_put_contents($backupDir.'/catalog-before-official-'.gmdate('Ymd-His').'.json',json_encode($before,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
 DB::commit();echo json_encode(['ok'=>true,'catalog'=>$summary,'school_profiles_unchanged'=>true,'history_unchanged'=>true]).PHP_EOL;
}catch(Throwable $e){DB::rollBack();throw $e;}

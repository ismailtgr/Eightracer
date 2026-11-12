<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
$root=dirname(__DIR__);$dir=$root.'/.migration-private/mysql-snapshot-20261112';
$c=Dotenv\Dotenv::createArrayBacked($root,'.env.staging')->load();
$p=new PDO("pgsql:host={$c['DB_HOST']};port={$c['DB_PORT']};dbname=postgres;sslmode=require",$c['DB_USERNAME'],$c['DB_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$m=json_decode(file_get_contents($dir.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);
function normal(mixed $v,string $type):mixed {
 if($v===null)return null;
 if($type==='boolean')return in_array($v,[true,1,'1','t','true'],true)?'1':'0';
 if(in_array($type,['json','jsonb'])){$v=is_string($v)?json_decode($v,true,512,JSON_THROW_ON_ERROR):$v;return $v;}
 if(in_array($type,['smallint','integer','bigint','numeric','real','double precision']))return rtrim(rtrim(number_format((float)$v,10,'.',''),'0'),'.');
 if(str_starts_with($type,'timestamp'))return preg_replace('/\.0+$/','',(string)$v);
 return (string)$v;
}
function fingerprint(array $rows,array $fields,array $types):string{
 $hashes=[];foreach($rows as $row){$r=[];foreach($fields as $k)$r[$k]=normal($row[$k],$types[$k]);$hashes[]=hash('sha256',json_encode($r,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));}sort($hashes);return hash('sha256',implode('',$hashes));
}
$p->beginTransaction();$p->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');$result=[];$ok=true;
foreach($m['tables'] as $table=>$info){if(!preg_match('/^[a-z0-9_]+$/D',$table))throw new RuntimeException('Unsafe table');
 $stmt=$p->prepare("SELECT column_name,data_type FROM information_schema.columns WHERE table_schema='public' AND table_name=?");$stmt->execute([$table]);$types=$stmt->fetchAll(PDO::FETCH_KEY_PAIR);
 $source=json_decode(file_get_contents($dir.'/'.$table.'.json'),true,512,JSON_THROW_ON_ERROR);$target=$p->query('SELECT * FROM "'.$table.'"')->fetchAll();
 $fields=array_column($info['columns'],'Field');if($table==='migrations')$fields=['migration'];
 $match=count($source)===count($target)&&fingerprint($source,$fields,$types)===fingerprint($target,$fields,$types);
 $result[$table]=['source'=>count($source),'target'=>count($target),'content_matches'=>$match];$ok=$ok&&$match;
}
$p->rollBack();file_put_contents($dir.'/verification.json',json_encode(['ok'=>$ok,'tables'=>$result],JSON_PRETTY_PRINT));
echo json_encode(['ok'=>$ok,'tables_checked'=>count($result),'mismatches'=>array_keys(array_filter($result,fn($r)=>!$r['content_matches']))]).PHP_EOL;exit($ok?0:1);

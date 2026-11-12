<?php
declare(strict_types=1);
// Explicit export/import phases. Never drops, truncates, seeds, or changes source records.
require dirname(__DIR__).'/vendor/autoload.php';
$root=dirname(__DIR__); $dir=$root.'/.migration-private/mysql-snapshot-20261112';
function conn(array $c): PDO {
 $pg=$c['DB_CONNECTION']==='pgsql';
 $dsn=$pg ? "pgsql:host={$c['DB_HOST']};port={$c['DB_PORT']};dbname={$c['DB_DATABASE']};sslmode=require;connect_timeout=15" : "mysql:host={$c['DB_HOST']};port={$c['DB_PORT']};dbname={$c['DB_DATABASE']};charset=utf8mb4";
 return new PDO($dsn,$c['DB_USERNAME'],$c['DB_PASSWORD']??'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
}
function ident(string $s, bool $pg=true):string {if(!preg_match('/^[a-zA-Z0-9_]+$/D',$s))throw new RuntimeException('Unsafe identifier');return $pg?'"'.$s.'"':'`'.$s.'`';}
$phase=$argv[1]??'';$table='';
try {
 if($phase==='export') {
  $c=Dotenv\Dotenv::createArrayBacked($root,'.env')->load();
  if(!in_array($c['DB_CONNECTION'],['mysql','mariadb']))throw new RuntimeException('Export requires active MySQL');
  if(is_file($dir.'/manifest.json'))throw new RuntimeException('Snapshot already exists; preserved');
  if(!is_dir($dir))mkdir($dir,0700,true);
  $db=conn($c);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('SET TRANSACTION READ ONLY');$db->beginTransaction();
  $tables=$db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
  $fk=$db->query("SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL")->fetchAll();
  $manifest=['created_at'=>gmdate('c'),'source'=>'mysql','tables'=>[],'foreign_keys'=>$fk];
  foreach($tables as $table) {
   $rows=$db->query('SELECT * FROM '.ident($table,false))->fetchAll();
   $json=json_encode($rows,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
   file_put_contents($dir.'/'.$table.'.json',$json);
   $manifest['tables'][$table]=['rows'=>count($rows),'sha256'=>hash('sha256',$json),'columns'=>$db->query('SHOW COLUMNS FROM '.ident($table,false))->fetchAll()];
  }
  $db->rollBack();file_put_contents($dir.'/manifest.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
  echo json_encode(['ok'=>true,'phase'=>'export','table_count'=>count($tables),'row_count'=>array_sum(array_column($manifest['tables'],'rows'))]).PHP_EOL;exit;
 }
 if($phase!=='import')throw new RuntimeException('Use export or import');
 $m=json_decode(file_get_contents($dir.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);
 $target=Dotenv\Dotenv::createArrayBacked($root,'.env.staging')->load();
 if(($target['DB_CONNECTION']??'')!=='pgsql'||!str_contains($target['DB_USERNAME'],'hitxegwqiezzomhibpwf'))throw new RuntimeException('Wrong migration destination');
 $db=conn($target);
 $cols=$db->query("SELECT table_name,column_name,data_type FROM information_schema.columns WHERE table_schema='public'")->fetchAll();$types=[];
 foreach($cols as $col)$types[$col['table_name']][$col['column_name']]=$col['data_type'];
 $todo=array_diff(array_keys($m['tables']),['migrations']);$order=[];
 foreach($todo as $table){
  if(!isset($types[$table]))throw new RuntimeException('Target missing table '.$table);
  foreach($m['tables'][$table]['columns'] as $col)if(!isset($types[$table][$col['Field']]))throw new RuntimeException('Target missing column '.$table.'.'.$col['Field']);
  if((int)$db->query('SELECT COUNT(*) FROM '.ident($table))->fetchColumn()!==0)throw new RuntimeException('Target table nonempty: '.$table);
 }
 while($todo){$progress=false;foreach($todo as $key=>$table){$deps=[];foreach($m['foreign_keys'] as $f)if($f['TABLE_NAME']===$table&&$f['REFERENCED_TABLE_NAME']!==$table)$deps[]=$f['REFERENCED_TABLE_NAME'];if(!array_intersect($deps,$todo)){$order[]=$table;unset($todo[$key]);$progress=true;}}if(!$progress)throw new RuntimeException('Cyclic foreign key dependency');}
 $db->beginTransaction();$counts=[];
 foreach($order as $table){
  $raw=file_get_contents($dir.'/'.$table.'.json');if(!hash_equals($m['tables'][$table]['sha256'],hash('sha256',$raw)))throw new RuntimeException('Snapshot checksum mismatch');
  $rows=json_decode($raw,true,512,JSON_THROW_ON_ERROR);$counts[$table]=count($rows);if(!$rows)continue;
  $fields=array_keys($rows[0]);$sql='INSERT INTO '.ident($table).' ('.implode(',',array_map('ident',$fields)).') VALUES ('.implode(',',array_fill(0,count($fields),'?')).')';$stmt=$db->prepare($sql);
  foreach($rows as $row){$params=[];foreach($fields as $field){$v=$row[$field];if($types[$table][$field]==='boolean'&&$v!==null)$v=(int)$v===1?'true':'false';$params[]=$v;}$stmt->execute($params);}
  $n=(int)$db->query('SELECT COUNT(*) FROM '.ident($table))->fetchColumn();if($n!==count($rows))throw new RuntimeException('Count mismatch');
 }
 $db->commit();
 foreach($order as $table){if(isset($types[$table]['id'])){$stmt=$db->prepare('SELECT pg_get_serial_sequence(?,?)');$stmt->execute(['public.'.$table,'id']);$seq=$stmt->fetchColumn();if($seq){$max=$db->query('SELECT MAX(id) FROM '.ident($table))->fetchColumn();$stmt=$db->prepare('SELECT setval(?::regclass,?,?)');$stmt->execute([$seq,$max===null?1:$max,$max===null?'false':'true']);}}}
 file_put_contents($dir.'/import-result.json',json_encode(['ok'=>true,'counts'=>$counts,'completed_at'=>gmdate('c')],JSON_PRETTY_PRINT));
 echo json_encode(['ok'=>true,'phase'=>'import','table_count'=>count($counts),'row_count'=>array_sum($counts)]).PHP_EOL;
} catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();$msg=$e->getMessage();file_put_contents($root.'/.migration-private/migration-error.txt',$msg);echo json_encode(['ok'=>false,'phase'=>$phase,'table'=>$table,'error_type'=>get_class($e),'sqlstate'=>$e instanceof PDOException?$e->getCode():null,'details'=>'Private migration-error.txt']).PHP_EOL;exit(1);}

<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';$root=dirname(__DIR__);$dir=$root.'/.migration-private/mysql-snapshot-20261112';
$verified=json_decode(file_get_contents($dir.'/verification.json'),true,512,JSON_THROW_ON_ERROR);if(!$verified['ok'])throw new RuntimeException('Copy not verified');
$c=Dotenv\Dotenv::createArrayBacked($root,'.env')->load();if(!in_array($c['DB_CONNECTION'],['mysql','mariadb']))throw new RuntimeException('Active source must be MySQL');
$m=json_decode(file_get_contents($dir.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);$source=new PDO("mysql:host={$c['DB_HOST']};port={$c['DB_PORT']};dbname={$c['DB_DATABASE']};charset=utf8mb4",$c['DB_USERNAME'],$c['DB_PASSWORD']??'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$source->exec('SET TRANSACTION READ ONLY');$source->beginTransaction();
foreach($m['tables'] as $t=>$info){$rows=$source->query('SELECT * FROM `'.$t.'`')->fetchAll();$hash=hash('sha256',json_encode($rows,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));if(!hash_equals($info['sha256'],$hash))throw new RuntimeException('Source changed since export: '.$t);}$source->rollBack();
$r=json_decode(file_get_contents($root.'/.migration-private/supabase-runtime.json'),true,512,JSON_THROW_ON_ERROR);$s=Dotenv\Dotenv::createArrayBacked($root,'.env.staging')->load();$s['DB_USERNAME']=$r['role'].'.hitxegwqiezzomhibpwf';$s['DB_PASSWORD']=$r['password'];
$p=new PDO("pgsql:host={$s['DB_HOST']};port=5432;dbname=postgres;sslmode=require",$s['DB_USERNAME'],$s['DB_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
foreach($verified['tables'] as $t=>$v)if((int)$p->query('SELECT COUNT(*) FROM "'.$t.'"')->fetchColumn()!==$v['target'])throw new RuntimeException('Runtime count mismatch: '.$t);
$p->beginTransaction();$key='migration-check-'.bin2hex(random_bytes(12));$st=$p->prepare('INSERT INTO cache (key,value,expiration) VALUES (?,?,?)');$st->execute([$key,'migration-check',time()+60]);$st=$p->prepare('UPDATE cache SET value=? WHERE key=?');$st->execute(['updated',$key]);$st=$p->prepare('DELETE FROM cache WHERE key=?');$st->execute([$key]);$p->rollBack();
$env=file_get_contents($root.'/.env');$backup=$root.'/.migration-private/env-before-supabase-20261112';if(!is_file($backup))file_put_contents($backup,$env);
foreach(['DB_CONNECTION','DB_HOST','DB_PORT','DB_DATABASE','DB_USERNAME','DB_PASSWORD','DB_CHARSET','DB_SSLMODE','DB_EMULATE_PREPARES','DB_URL'] as $k){$line=$k.'='.($s[$k]??'');if(preg_match('/^'.preg_quote($k,'/').'=.*$/m',$env))$env=preg_replace_callback('/^'.preg_quote($k,'/').'=.*$/m',fn()=>$line,$env);else $env.=PHP_EOL.$line;}
file_put_contents($root.'/.env',$env);echo json_encode(['ok'=>true,'active_database'=>'supabase_postgres','mysql_preserved'=>true,'source_unchanged'=>true,'runtime_crud_rollback'=>true,'tables'=>count($verified['tables'])]).PHP_EOL;

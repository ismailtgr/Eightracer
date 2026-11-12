<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
$root=dirname(__DIR__);$c=Dotenv\Dotenv::createArrayBacked($root,'.env.staging')->load();
if(!str_contains($c['DB_USERNAME'],'eightracer_migrator.')||!str_contains($c['DB_USERNAME'],'hitxegwqiezzomhibpwf'))throw new RuntimeException('Explicit project migrator required');
$p=new PDO("pgsql:host={$c['DB_HOST']};port={$c['DB_PORT']};dbname=postgres;sslmode=require",$c['DB_USERNAME'],$c['DB_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$tables=$p->query("SELECT tablename FROM pg_tables WHERE schemaname='public' AND tableowner=current_user")->fetchAll(PDO::FETCH_COLUMN);
$p->beginTransaction();
foreach($tables as $t){if(!preg_match('/^[a-z0-9_]+$/D',$t))throw new RuntimeException('Unsafe identifier');$q='public."'.$t.'"';
 $p->exec('REVOKE ALL ON '.$q.' FROM PUBLIC, anon, authenticated');
 $p->exec('ALTER TABLE '.$q.' ENABLE ROW LEVEL SECURITY');
 $p->exec('DROP POLICY IF EXISTS backend_runtime_access ON '.$q);
 $p->exec('CREATE POLICY backend_runtime_access ON '.$q.' TO eightracer_runtime USING (true) WITH CHECK (true)');
 $p->exec('GRANT '.($t==='migrations'?'SELECT':'SELECT, INSERT, UPDATE, DELETE').' ON '.$q.' TO eightracer_runtime');
}
$p->exec('REVOKE ALL ON ALL SEQUENCES IN SCHEMA public FROM PUBLIC, anon, authenticated');
$p->exec('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO eightracer_runtime');
$p->exec('ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE ALL ON TABLES FROM PUBLIC, anon, authenticated');
$p->exec('ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE ALL ON SEQUENCES FROM PUBLIC, anon, authenticated');
$p->commit();echo json_encode(['ok'=>true,'protected_tables'=>count($tables),'runtime'=>'backend_only','public_data_access'=>false]).PHP_EOL;

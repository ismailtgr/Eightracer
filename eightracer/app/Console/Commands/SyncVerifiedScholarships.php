<?php

namespace App\Console\Commands;

use App\Models\BasisDataBeasiswa;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncVerifiedScholarships extends Command
{
    protected $signature = 'scholarships:sync-verified {--apply : Apply after checking identities; default is dry run}';
    protected $description = 'Synchronize curated official references without altering alumni, criteria, dates or archive status';

    public function handle(): int
    {
        $dataset = require database_path('data/scholarships/verified-20261114.php');
        $apply = (bool) $this->option('apply');
        DB::beginTransaction();
        try {
            $query = BasisDataBeasiswa::with('kriteriaKelayakanBeasiswa')->whereIn('id', array_column($dataset['items'], 'id'));
            if ($apply) $query->lockForUpdate();
            $rows = $query->get()->keyBy('id');
            foreach ($dataset['items'] as $item) {
                $row = $rows->get($item['id']);
                if (! $row || $row->nama_beasiswa !== $item['name'] || $row->penyelenggara !== $item['provider']) {
                    throw new \RuntimeException('Catalog identity mismatch; nothing changed.');
                }
            }
            if ($apply) {
                $directory = storage_path('app/private/catalog-backups');
                if (!is_dir($directory) && !mkdir($directory,0700,true)) throw new \RuntimeException('Backup directory unavailable.');
                $file = $directory.'/verified-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4)).'.json';
                $backup = json_encode(['version'=>$dataset['version'],'rows'=>$rows->values()->toArray()],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
                if (file_put_contents($file,$backup,LOCK_EX) === false) throw new \RuntimeException('Backup failed; nothing changed.');
                foreach ($dataset['items'] as $item) {
                    $row=$rows[$item['id']];
                    $policy=$row->official_policy ?? [];
                    $policy=array_replace($policy,[
                        'version'=>$dataset['version'],'verified_at'=>'2026-11-14',
                        'reference_period'=>2026,'verification_scope'=>$item['id']===8 ? 'program_benefits_only' : 'published_requirements_2026',
                        'sources'=>$item['sources'],'manual_requirements'=>$item['manual_requirements'],
                        'scope_checks'=>$item['scope_checks'],'alternative_paths'=>[],
                        'review_required'=>false,
                    ]);
                    $row->forceFill(['deskripsi'=>$item['description'],'official_policy'=>$policy])->save();
                }
                DB::commit();
            } else DB::rollBack();
            $this->line(json_encode(['status'=>$apply?'applied':'dry_run','version'=>$dataset['version'],'programs'=>count($dataset['items']),
                'student_or_alumni_changes'=>0,'criteria_changes'=>0,'archive_or_date_changes'=>0],JSON_PRETTY_PRINT));
            return self::SUCCESS;
        } catch (\Throwable $error) {
            DB::rollBack();
            $this->error('Synchronization stopped. '.$error->getMessage());
            return self::FAILURE;
        }
    }
}

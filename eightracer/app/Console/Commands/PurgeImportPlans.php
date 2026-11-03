<?php

namespace App\Console\Commands;

use App\Models\SchoolImportBatch;
use Illuminate\Console\Command;

class PurgeImportPlans extends Command
{
    protected $signature = 'school:purge-import-plans';

    protected $description = 'Hapus payload pratinjau impor yang kedaluwarsa; pertahankan metadata audit';

    public function handle(): int
    {
        $n = SchoolImportBatch::where('expires_at', '<=', now())->whereIn('status', ['validated', 'invalid', 'failed'])->update(['plan' => null, 'status' => 'expired']);
        $this->info('Payload dibersihkan: '.$n);

        return self::SUCCESS;
    }
}

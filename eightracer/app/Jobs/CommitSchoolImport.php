<?php

namespace App\Jobs;

use App\Models\SchoolImportBatch;
use App\Services\School\BulkImportEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CommitSchoolImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public string $batchId) {}

    public function handle(BulkImportEngine $engine): void
    {
        $engine->commit($this->batchId);
    }

    public function failed(?\Throwable $error): void
    {
        SchoolImportBatch::whereKey($this->batchId)->where('status', 'queued')->update(['status' => 'failed', 'receipt' => json_encode(['processed' => 0, 'code' => 'queue_worker_failed'])]);
    }
}

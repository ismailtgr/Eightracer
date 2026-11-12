<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class InternalSchedulerController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $expected = config('scheduler.scheduler_secret');
        $provided = $request->header('X-Scheduler-Secret');
        // Empty/malformed server configuration must never authenticate an empty header.
        abort_unless(is_string($expected) && strlen($expected) >= 32
            && is_string($provided) && $provided !== '' && hash_equals($expected, $provided), 403);

        $lock = Cache::lock('internal:scheduler:run', (int) config('scheduler.lock_seconds', 300));
        abort_unless($lock->get(), 409);
        try {
            $exitCode = Artisan::call('schedule:run');
            abort_unless($exitCode === 0, 503);
            return response()->json(['ok' => true]);
        } catch (\Throwable) {
            // Command output may contain private data; never return it over HTTP.
            abort(503, 'Scheduler sementara belum tersedia.');
        } finally {
            $lock->release();
        }
    }
}

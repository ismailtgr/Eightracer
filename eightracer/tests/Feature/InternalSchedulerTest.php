<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class InternalSchedulerTest extends TestCase
{
    private const SECRET = 'synthetic-scheduler-test-secret-1234567890';

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'scheduler.scheduler_secret' => self::SECRET]);
        Cache::flush();
    }

    public function test_empty_or_invalid_secret_never_runs_scheduler(): void
    {
        Artisan::shouldReceive('call')->never();
        $this->postJson('/api/internal/run-scheduler')->assertForbidden();
        $this->postJson('/api/internal/run-scheduler', [], ['X-Scheduler-Secret' => 'wrong'])->assertForbidden();
        config(['scheduler.scheduler_secret' => '']);
        $this->postJson('/api/internal/run-scheduler')->assertForbidden();
        config(['scheduler.scheduler_secret' => 'short']);
        $this->postJson('/api/internal/run-scheduler', [], ['X-Scheduler-Secret' => 'short'])->assertForbidden();
    }

    public function test_query_parameter_secret_is_not_accepted(): void
    {
        Artisan::shouldReceive('call')->never();
        $this->postJson('/api/internal/run-scheduler?secret='.self::SECRET)->assertForbidden();
    }

    public function test_valid_header_runs_schedule_once_and_returns_no_command_output(): void
    {
        Artisan::shouldReceive('call')->once()->with('schedule:run')->andReturn(0);
        $this->postJson('/api/internal/run-scheduler', [], ['X-Scheduler-Secret' => self::SECRET])
            ->assertOk()->assertExactJson(['ok' => true])->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_concurrent_trigger_is_rejected_without_running_commands(): void
    {
        $lock = Cache::lock('internal:scheduler:run', 300);
        $this->assertTrue($lock->get());
        Artisan::shouldReceive('call')->never();
        try {
            $this->postJson('/api/internal/run-scheduler', [], ['X-Scheduler-Secret' => self::SECRET])->assertStatus(409);
        } finally { $lock->release(); }
    }

    public function test_command_failure_is_generic_and_releases_lock(): void
    {
        Artisan::shouldReceive('call')->once()->with('schedule:run')->andThrow(new \RuntimeException('PRIVATE_SERVER_OUTPUT'));
        $this->postJson('/api/internal/run-scheduler', [], ['X-Scheduler-Secret' => self::SECRET])
            ->assertStatus(503)->assertDontSee('PRIVATE_SERVER_OUTPUT');
        $lock = Cache::lock('internal:scheduler:run', 300);
        $this->assertTrue($lock->get()); $lock->release();
    }

    public function test_nonzero_exit_is_not_reported_as_success(): void
    {
        Artisan::shouldReceive('call')->once()->with('schedule:run')->andReturn(1);
        $this->postJson('/api/internal/run-scheduler', [], ['X-Scheduler-Secret' => self::SECRET])->assertStatus(503);
    }

    public function test_scheduler_trigger_is_rate_limited(): void
    {
        Artisan::shouldReceive('call')->times(6)->with('schedule:run')->andReturn(0);
        for ($n = 0; $n < 6; $n++) {
            $this->postJson('/api/internal/run-scheduler', [], ['X-Scheduler-Secret' => self::SECRET])->assertOk();
        }
        $this->postJson('/api/internal/run-scheduler', [], ['X-Scheduler-Secret' => self::SECRET])->assertStatus(429);
    }

    public function test_cors_preflight_uses_plural_frontend_urls_and_bearer_headers(): void
    {
        config(['cors.allowed_origins' => ['https://frontend.example.test', 'https://second-frontend.example.test']]);
        $this->options('/api/faq', [], ['Origin' => 'https://frontend.example.test',
            'Access-Control-Request-Method' => 'GET', 'Access-Control-Request-Headers' => 'authorization,content-type'])
            ->assertStatus(204)->assertHeader('Access-Control-Allow-Origin', 'https://frontend.example.test')
            ->assertHeaderMissing('Access-Control-Allow-Credentials');
        $this->options('/api/faq', [], ['Origin' => 'https://untrusted.example.test',
            'Access-Control-Request-Method' => 'GET'])->assertHeaderMissing('Access-Control-Allow-Origin');
    }
}

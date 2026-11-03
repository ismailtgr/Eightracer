<?php

namespace Tests\Feature;

use App\Services\Auth\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class LocalInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_https_is_required_when_local_enforcement_is_enabled(): void
    {
        config(['security.require_https' => true, 'security.trusted_proxies' => []]);
        $this->getJson('/api/faq')->assertForbidden();
        $this->getJson('https://localhost/api/faq')->assertOk();
    }

    public function test_only_the_configured_loopback_proxy_can_assert_https(): void
    {
        config(['security.require_https' => true, 'security.trusted_proxies' => ['127.0.0.1', '::1']]);
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10', 'HTTP_X_FORWARDED_PROTO' => 'https'])->getJson('/api/faq')->assertForbidden();
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_PROTO' => 'https'])->getJson('/api/faq')->assertOk();
    }

    public function test_capture_email_is_available_for_local_test_but_never_enables_production_delivery(): void
    {
        config(['prd.email_otp_enabled' => true, 'mail.default' => 'smtp', 'security.mail_capture_only' => true]);
        app(OtpService::class)->ready();
        $this->app->instance('env', 'production');
        try {
            app(OtpService::class)->ready();
            $this->fail('Production must not rely on local email capture.');
        } catch (HttpException $e) {
            $this->assertSame(503, $e->getStatusCode());
        }
    }
}

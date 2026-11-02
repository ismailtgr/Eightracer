<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScholarshipApiContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE=:memory:');
        parent::setUp();
        config(['cache.default' => 'array']);
    }

    public function test_unauthenticated_api_is_json_even_without_accept_header(): void
    {
        $this->get('/api/rekomendasi-beasiswa')->assertUnauthorized()->assertJsonStructure(['message']);
    }

    public function test_frontend_preflight_allows_only_configured_origins(): void
    {
        config(['cors.allowed_origins' => ['http://localhost:5173']]);
        $this->options('/api/beasiswa', [], [
            'Origin' => 'http://localhost:5173',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'authorization,content-type',
        ])->assertSuccessful()->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
        $response = $this->options('/api/beasiswa', [], [
            'Origin' => 'https://not-configured.example',
            'Access-Control-Request-Method' => 'POST',
        ]);
        $this->assertNotSame('https://not-configured.example', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_bearer_token_login_me_and_logout_contract(): void
    {
        $user = User::forceCreate(['username' => 'contract-admin', 'email' => 'contract-admin@example.test', 'email_verified_at' => now(), 'password' => 'contract-password', 'role' => 'administrator']);
        $token = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'contract-password'])
            ->assertOk()->assertJsonPath('user.role', 'administrator')->json('token');
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertOk()->assertJsonPath('id', $user->id);
        $this->withToken($token)->postJson('/api/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}

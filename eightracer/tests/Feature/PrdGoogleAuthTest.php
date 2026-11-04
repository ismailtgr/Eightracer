<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\PrdFixtures;
use Tests\TestCase;

class PrdGoogleAuthTest extends TestCase
{
    use PrdFixtures,RefreshDatabase;

    private static $key;

    protected function setUp(): void
    {
        parent::setUp();
        config(['prd.google_client_id' => 'synthetic-client.apps.googleusercontent.com', 'cache.default' => 'array']);
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        if (is_file(base_path('.tools/php84/extras/ssl/openssl.cnf'))) {
            $options['config'] = base_path('.tools/php84/extras/ssl/openssl.cnf');
        }
        self::$key ??= openssl_pkey_new($options);
        $this->assertNotFalse(self::$key);
        Http::preventStrayRequests();
        Http::fake(['www.googleapis.com/oauth2/v1/certs' => Http::response(['test-kid' => openssl_pkey_get_details(self::$key)['key']])]);
    }

    private function body(array $claims = [], array $header = []): array
    {
        $c = $this->postJson('/api/auth/google/challenge')->assertOk()->json();
        $enc = fn ($s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $parts = [$enc(json_encode([...['alg' => 'RS256', 'kid' => 'test-kid'], ...$header])), $enc(json_encode([...['iss' => 'https://accounts.google.com', 'aud' => config('prd.google_client_id'), 'iat' => now()->timestamp, 'exp' => now()->addHour()->timestamp, 'nonce' => $c['nonce'], 'email_verified' => true, 'email' => 'synthetic@gmail.com', 'sub' => 'test-subject', 'name' => 'Synthetic Name'], ...$claims]))];
        openssl_sign(implode('.', $parts), $signature, self::$key, OPENSSL_ALGO_SHA256);

        return ['challenge_id' => $c['challenge_id'], 'id_token' => implode('.', $parts).'.'.$enc($signature)];
    }

    public function test_google_cannot_register_or_claim_an_unprovisioned_student(): void
    {
        $student = $this->student(null, ['registration_email' => 'synthetic@gmail.com']);
        $this->postJson('/api/auth/google', $this->body())->assertForbidden();
        $this->assertDatabaseCount('users', 0);
        $this->assertNull($student->fresh()->user_id);
        $this->postJson('/api/auth/google', [...$this->body(), 'nisn' => $student->nisn])->assertUnprocessable();
    }

    public function test_bad_signature_audience_expiry_nonce_issuer_and_unverified_email_are_rejected(): void
    {
        foreach ([['aud' => 'attacker-app'], ['exp' => now()->subSecond()->timestamp], ['nonce' => 'wrong'], ['iss' => 'attacker.example'], ['email_verified' => false]] as $claims) {
            Cache::flush();
            $this->postJson('/api/auth/google', $this->body($claims))->assertUnprocessable();
        }
        Cache::flush();
        $body = $this->body();
        $body['id_token'] = substr($body['id_token'], 0, -20).'invalid-signature';
        $this->postJson('/api/auth/google', $body)->assertUnprocessable();
        Cache::flush();
        $this->postJson('/api/auth/google', $this->body([], ['alg' => 'none']))->assertUnprocessable();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_only_preprovisioned_verified_admin_can_login_via_google_without_ending_other_devices(): void
    {
        $u = $this->account('administrator', ['email' => 'synthetic@gmail.com']);
        $old = $this->signIn($u);
        $this->postJson('/api/auth/google', $this->body())->assertOk()->assertJsonPath('user.role', 'administrator');
        $this->assertSame('test-subject', $u->fresh()->google_id);
        $this->assertSame(2, $u->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->withToken($old)->getJson('/api/me')->assertOk();
        $this->assertDatabaseCount('users', 1);
        $this->assertLessThanOrEqual(now()->addHours(72)->timestamp, $u->tokens()->first()->expires_at->timestamp);
    }

    public function test_unknown_google_cannot_create_admin_or_claim_wrong_student(): void
    {
        $s = $this->student(null, ['registration_email' => 'someoneelse@gmail.com']);
        $this->postJson('/api/auth/google', [...$this->body(), 'nisn' => $s->nisn])->assertUnprocessable();
        $this->postJson('/api/auth/google', [...$this->body(), 'role' => 'administrator'])->assertUnprocessable();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_third_party_google_email_requires_existing_account_password_before_link(): void
    {
        $u = $this->account('administrator', ['email' => 'synthetic@example.test']);
        $this->postJson('/api/auth/google', $this->body(['email' => $u->email]))->assertStatus(409);
        $this->assertNull($u->fresh()->google_id);
        $this->signIn($u);
        $this->postJson('/api/profile/google', [...$this->body(['email' => $u->email]), 'current_password' => 'wrong'])->assertUnprocessable();
        $this->postJson('/api/profile/google', [...$this->body(['email' => $u->email]), 'current_password' => 'Strong-Test123!'])->assertOk();
        $this->postJson('/api/auth/google', $this->body(['email' => $u->email]))->assertOk()->assertJsonPath('user.role', 'administrator');
    }
}

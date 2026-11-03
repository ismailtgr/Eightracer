<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\BasisDataBeasiswa;
use Tests\Concerns\PrdFixtures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScholarshipApiContractTest extends TestCase
{
    use RefreshDatabase, PrdFixtures;

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

    public function test_catalog_edit_lifecycle_preserves_archive_and_replaces_criteria(): void
    {
        $admin = User::forceCreate(['username' => 'edit-admin', 'email' => 'edit-admin@example.test', 'email_verified_at' => now(), 'password' => 'contract-password', 'role' => 'administrator']);
        $this->signIn($admin);
        $payload = ['nama_beasiswa' => 'UJI Kontrak', 'jenjang' => 'SMA', 'is_active' => true,
            'kriteria' => [['nama_kriteria' => 'Rapor', 'tipe_kriteria' => 'rata_rapor', 'operator' => '>=', 'nilai_kriteria' => '85', 'bobot' => 2]]];
        $id = $this->postJson('/api/beasiswa', $payload)->assertCreated()->json('data.id');
        $this->getJson('/api/beasiswa/'.$id)->assertOk()->assertJsonPath('data.nama_beasiswa', 'UJI Kontrak')
            ->assertJsonPath('data.kriteria_kelayakan_beasiswa.0.tipe_kriteria', 'rata_rapor');
        $this->deleteJson('/api/beasiswa/'.$id)->assertOk();
        $this->getJson('/api/beasiswa/'.$id)->assertOk()->assertJsonPath('data.is_active', false);
        $payload['kriteria'][0]['nilai_kriteria'] = '90';
        $this->putJson('/api/beasiswa/'.$id, $payload)->assertOk()->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.kriteria_kelayakan_beasiswa.0.nilai_kriteria', '90');
        $this->assertDatabaseCount('basis_data_beasiswa', 1);
        $this->assertDatabaseCount('kriteria_kelayakan_beasiswa', 1);
        $this->getJson('/api/beasiswa/999999')->assertNotFound();
    }

    public function test_student_cannot_read_or_mutate_staff_catalog(): void
    {
        $scholarship = BasisDataBeasiswa::create(['nama_beasiswa' => 'UJI Privat', 'jenjang' => 'SMA', 'is_active' => true]);
        $student = User::forceCreate(['username' => 'edit-student', 'email' => 'edit-student@example.test', 'email_verified_at' => now(), 'password' => 'contract-password', 'role' => 'siswa']);
        $this->signIn($student);
        $this->getJson('/api/beasiswa/'.$scholarship->id)->assertForbidden();
        $this->putJson('/api/beasiswa/'.$scholarship->id, [])->assertForbidden();
        $this->deleteJson('/api/beasiswa/'.$scholarship->id)->assertForbidden();
        $this->assertDatabaseHas('basis_data_beasiswa', ['id' => $scholarship->id, 'is_active' => true]);
    }
}

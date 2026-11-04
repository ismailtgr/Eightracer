<?php

namespace Tests\Feature;

use App\Models\BasisDataBeasiswa;
use App\Models\Kelas;
use App\Models\RekomendasiBeasiswa;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiScholarshipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        // Always an isolated SQLite database, never the project's configured database.
        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE=:memory:');
        parent::setUp();
        config(['gemini.api_key' => 'test-key', 'cache.default' => 'array']);
        Http::preventStrayRequests();
    }

    private function signIn(User $user): void
    {
        $this->app['auth']->forgetGuards();
        $this->withToken($user->createToken('test', ['*'], now()->addHour())->plainTextToken);
    }

    private function user(string $role = 'siswa'): User
    {
        return User::forceCreate(['username' => uniqid('test'), 'email' => uniqid('test').'@example.test', 'email_verified_at' => now(), 'password' => 'password-test', 'role' => $role]);
    }

    private function student(User $user): Siswa
    {
        $student = Siswa::create(['user_id' => $user->id, 'nisn' => uniqid(), 'nama_lengkap' => 'PRIVATE_NAME', 'jenis_kelamin' => 'L', 'nilai_tka' => 90]);
        $kelas = Kelas::create(['tingkat' => 12, 'nama_kelas' => uniqid('XII'), 'tahun_ajaran' => '2026/2027']);
        $student->riwayatKelas()->create(['kelas_id' => $kelas->id, 'tanggal_mulai' => '2026-07-01']);

        return $student;
    }

    private function scholarship(): BasisDataBeasiswa
    {
        $b = BasisDataBeasiswa::create(['nama_beasiswa' => 'Beasiswa Contoh', 'jenjang' => 'SMA', 'is_active' => true]);
        $b->kriteriaKelayakanBeasiswa()->create(['nama_kriteria' => 'TKA minimal', 'tipe_kriteria' => 'nilai_tka', 'operator' => '>=', 'nilai_kriteria' => '80', 'bobot' => 1]);

        return $b;
    }

    private function fakeGemini(int $id): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['status' => 'completed', 'steps' => [[
            'type' => 'model_output', 'content' => [['type' => 'text', 'text' => json_encode(['rekomendasi' => [[
                'beasiswa_id' => $id, 'alasan' => 'Memenuhi syarat TKA pada katalog.', 'persiapan' => ['Siapkan dokumen sesuai katalog.'],
            ]]])]],
        ]]])]);
    }

    public function test_generate_review_and_student_isolation(): void
    {
        $owner = $this->user();
        $siswa = $this->student($owner);
        $b = $this->scholarship();
        $this->fakeGemini($b->id);
        $this->signIn($this->user('administrator'));
        $draft = $this->postJson("/api/siswa/{$siswa->id}/rekomendasi-beasiswa/generate")
            ->assertCreated()->assertJsonPath('data.status_review', 'pending')->json('data.id');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v1beta/interactions') && $r['store'] === false
            && ! str_contains(json_encode($r->data()), 'PRIVATE_NAME')
            && ! str_contains(json_encode($r->data()), $siswa->nisn));
        $this->signIn($owner);
        $this->getJson('/api/rekomendasi-beasiswa/saya')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson("/api/siswa/{$siswa->id}/rekomendasi-beasiswa/generate")->assertForbidden();
        $this->signIn($this->user('administrator'));
        $this->patchJson("/api/rekomendasi-beasiswa/{$draft}/review", ['status_review' => 'disetujui', 'catatan_admin' => 'PRIVATE_NOTE'])->assertOk();
        $this->signIn($owner);
        $this->getJson('/api/rekomendasi-beasiswa/saya')->assertOk()->assertJsonCount(1, 'data')->assertDontSee('PRIVATE_NOTE');
        $this->signIn($this->user());
        $this->getJson('/api/rekomendasi-beasiswa/saya')->assertNotFound();
        $other = $this->user();
        $this->student($other);
        $this->signIn($other);
        $this->getJson('/api/rekomendasi-beasiswa/saya?siswa_id='.$siswa->id)->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_bad_provider_output_never_creates_draft(): void
    {
        $siswa = $this->student($this->user());
        $this->scholarship();
        $this->fakeGemini(999999);
        $this->signIn($this->user('administrator'));
        $this->postJson("/api/siswa/{$siswa->id}/rekomendasi-beasiswa/generate")->assertStatus(503);
        $this->assertSame(0, RekomendasiBeasiswa::count());
    }

    public function test_stale_draft_cannot_be_published(): void
    {
        $siswa = $this->student($this->user());
        $b = $this->scholarship();
        $this->fakeGemini($b->id);
        $this->signIn($this->user('administrator'));
        $id = $this->postJson("/api/siswa/{$siswa->id}/rekomendasi-beasiswa/generate")->assertCreated()->json('data.id');
        $siswa->update(['nilai_tka' => 50]);
        $this->patchJson("/api/rekomendasi-beasiswa/{$id}/review", ['status_review' => 'disetujui'])->assertStatus(409);
        $this->assertDatabaseHas('rekomendasi_beasiswa', ['id' => $id, 'status_review' => 'pending']);
    }

    public function test_self_registration_cannot_create_an_admin(): void
    {
        $this->postJson('/api/register', ['username' => 'intruder', 'password' => 'password123', 'password_confirmation' => 'password123', 'role' => 'administrator'])->assertStatus(410);
    }

    public function test_missing_data_cannot_be_published_and_archiving_preserves_draft(): void
    {
        $siswa = $this->student($this->user());
        $b = $this->scholarship();
        $b->kriteriaKelayakanBeasiswa()->create(['nama_kriteria' => 'Ekonomi', 'tipe_kriteria' => 'penghasilan_orang_tua', 'operator' => '<=', 'nilai_kriteria' => '3000000']);
        $this->fakeGemini($b->id);
        $this->signIn($this->user('administrator'));
        $id = $this->postJson("/api/siswa/{$siswa->id}/rekomendasi-beasiswa/generate")->assertCreated()->json('data.id');
        $this->patchJson("/api/rekomendasi-beasiswa/{$id}/review", ['status_review' => 'disetujui'])->assertUnprocessable();
        $this->deleteJson("/api/beasiswa/{$b->id}")->assertOk();
        $this->assertDatabaseHas('basis_data_beasiswa', ['id' => $b->id, 'is_active' => false]);
        $this->assertDatabaseHas('rekomendasi_beasiswa', ['id' => $id, 'status_review' => 'pending']);
    }

    public function test_provider_quota_failure_does_not_save_or_expose_key(): void
    {
        $siswa = $this->student($this->user());
        $this->scholarship();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => 'test-key'], 429)]);
        $this->signIn($this->user('administrator'));
        $this->postJson("/api/siswa/{$siswa->id}/rekomendasi-beasiswa/generate")->assertStatus(503)->assertDontSee('test-key');
        $this->assertDatabaseCount('rekomendasi_beasiswa', 0);
    }

    public function test_incomplete_interaction_is_not_saved_as_a_draft(): void
    {
        $siswa = $this->student($this->user());
        $this->scholarship();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['status' => 'in_progress', 'steps' => []])]);
        $this->signIn($this->user('administrator'));
        $this->postJson("/api/siswa/{$siswa->id}/rekomendasi-beasiswa/generate")->assertStatus(503);
        $this->assertDatabaseCount('rekomendasi_beasiswa', 0);
    }

    public function test_expired_catalog_is_excluded_without_calling_ai(): void
    {
        $siswa = $this->student($this->user());
        $this->scholarship()->update(['tanggal_tutup' => today()->subDay()]);
        $this->signIn($this->user('administrator'));
        $this->postJson("/api/siswa/{$siswa->id}/rekomendasi-beasiswa/generate")->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_catalog_accepts_deadline_without_start_and_rejects_invalid_threshold(): void
    {
        $this->signIn($this->user('administrator'));
        $body = [
            'nama_beasiswa' => 'Katalog uji', 'jenjang' => 'SMA', 'tanggal_tutup' => today()->addMonth()->toDateString(),
            'kriteria' => [['nama_kriteria' => 'TKA', 'tipe_kriteria' => 'nilai_tka', 'operator' => '>=', 'nilai_kriteria' => '80']],
        ];
        $this->postJson('/api/beasiswa', $body)->assertCreated();
        $body['kriteria'][0]['nilai_kriteria'] = 'bukan angka';
        $this->postJson('/api/beasiswa', $body)->assertUnprocessable();
        $this->signIn($this->user());
        $this->postJson('/api/beasiswa', $body)->assertForbidden();
    }
}

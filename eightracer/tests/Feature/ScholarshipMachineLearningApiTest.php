<?php

namespace Tests\Feature;

use App\Models\BasisDataBeasiswa;
use App\Models\Hobi;
use App\Models\RekomendasiBeasiswa;
use App\Services\Beasiswa\ScholarshipInterestProfile;
use App\Services\Beasiswa\ScholarshipRecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\PrdFixtures;
use Tests\TestCase;

class ScholarshipMachineLearningApiTest extends TestCase
{
    use PrdFixtures, RefreshDatabase;

    private function fixture(): array
    {
        $owner = $this->account();
        $s = $this->student($owner, ['nilai_tka' => 90, 'nama_lengkap' => 'PRIVATE_IDENTITY', 'catatan' => 'PRIVATE_SOURCE_NOTES']);
        $this->classroom($s);
        $s->hobi()->attach(Hobi::create(['nama_hobi' => 'bulu tangkis atlet PRIVATE_INTEREST_ONLY'])->id);
        $items = [];
        foreach ([['sains matematika fisika', 80], ['olahraga bulu tangkis atlet', 80], ['olahraga bulu tangkis atlet', 99]] as $i => [$text, $threshold]) {
            $b = BasisDataBeasiswa::create(['nama_beasiswa' => 'Katalog sintetis '.($i + 1), 'deskripsi' => $text, 'jenjang' => 'SMA', 'is_active' => true]);
            $b->kriteriaKelayakanBeasiswa()->create(['nama_kriteria' => 'TKA', 'tipe_kriteria' => 'nilai_tka', 'operator' => '>=', 'nilai_kriteria' => (string) $threshold, 'bobot' => 1]);
            $items[] = $b;
        }
        Http::preventStrayRequests();

        return [$s, $owner, $items];
    }

    public function test_admin_preview_ranks_locally_without_ai_or_database_writes(): void
    {
        [$s, , $items] = $this->fixture();
        $this->signIn($this->account('administrator'));
        $data = $this->getJson("/api/siswa/{$s->id}/rekomendasi-beasiswa/preview")->assertOk()
            ->assertJsonPath('data.pemeringkatan_ml.status', 'applied')->assertDontSee('PRIVATE_IDENTITY')->assertDontSee('PRIVATE_SOURCE_NOTES')->assertDontSee('PRIVATE_INTEREST_ONLY')->json('data');
        $this->assertSame([$items[1]->id, $items[0]->id, $items[2]->id], array_column($data['kandidat'], 'beasiswa_id'));
        $this->assertSame(100, $data['kandidat'][0]['evaluasi']['skor']);
        $this->assertFalse($data['kandidat'][2]['evaluasi']['memenuhi']);
        $this->assertArrayNotHasKey('source_fingerprint', $data);
        $this->assertDatabaseCount('rekomendasi_beasiswa', 0);
        Http::assertNothingSent();
    }

    public function test_preview_enforces_authentication_role_and_target_validation(): void
    {
        [$s, $owner] = $this->fixture();
        $path = "/api/siswa/{$s->id}/rekomendasi-beasiswa/preview";
        $this->getJson($path)->assertUnauthorized();
        $this->signIn($owner);
        $this->getJson($path)->assertForbidden();
        $this->signIn($this->account('guru'));
        $this->getJson($path)->assertForbidden();
        $this->signIn($this->account('administrator'));
        $this->getJson($path.'?target_jenjang=PhD')->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_pending_prestasi_and_source_notes_do_not_become_ml_features(): void
    {
        $s = $this->student(null, ['catatan' => 'PRIVATE_NOTES olahraga atlet', 'agama' => 'PRIVATE_RELIGION']);
        $p = $s->prestasiMurid()->create(['jenis_prestasi' => 'Non-Akademik', 'nama_kompetisi' => 'PRIVATE_COMPETITION', 'bidang' => 'bulu tangkis', 'tingkat' => 'Nasional', 'status_verifikasi' => 'pending', 'tahun' => 2026]);
        $profile = app(ScholarshipInterestProfile::class);
        $this->assertSame('', $profile->document($s->fresh()));
        $p->update(['status_verifikasi' => 'terverifikasi']);
        $this->assertSame('bulu tangkis', $profile->document($s->fresh()));
    }

    public function test_generation_preserves_ml_metadata_and_keeps_interests_out_of_provider_payload(): void
    {
        [$s, $owner] = $this->fixture();
        config(['gemini.api_key' => 'synthetic-test-key']);
        Http::fake(['generativelanguage.googleapis.com/*' => function ($request) {
            $input = json_decode($request['input'], true);
            $this->assertStringNotContainsString('PRIVATE_', $request['input']);
            $this->assertStringNotContainsString('pemeringkatan_ml', $request['input']);
            $text = json_encode(['rekomendasi' => array_map(fn ($c) => ['beasiswa_id' => $c['beasiswa_id'], 'alasan' => 'Kriteria mengikuti katalog.', 'persiapan' => ['Periksa persyaratan resmi.']], $input)]);

            return Http::response(['status' => 'completed', 'steps' => [['type' => 'model_output', 'content' => [['type' => 'text', 'text' => $text]]]]]);
        }]);
        $this->signIn($this->account('administrator'));
        $id = $this->postJson("/api/siswa/{$s->id}/rekomendasi-beasiswa/generate")->assertCreated()
            ->assertJsonPath('data.rekomendasi_beasiswa_detail.0.evaluasi_kriteria.pemeringkatan_ml.status', 'applied')->json('data.id');
        $this->patchJson("/api/rekomendasi-beasiswa/{$id}/review", ['status_review' => 'disetujui'])->assertOk();
        $this->signIn($owner);
        $this->getJson('/api/rekomendasi-beasiswa/saya')->assertOk()
            ->assertJsonPath('data.0.beasiswa.0.pemeringkatan_ml.metode', 'tfidf-cosine-v1')->assertDontSee('model_hash')->assertDontSee('PRIVATE_');
    }

    public function test_interest_changes_invalidate_pending_draft_fingerprint(): void
    {
        [$s] = $this->fixture();
        $service = app(ScholarshipRecommendationService::class);
        [, , $old] = $service->sources($s->fresh());
        $draft = RekomendasiBeasiswa::create(['siswa_id' => $s->id, 'tanggal_rekomendasi' => today(), 'target_jenjang' => 'SMA', 'status_review' => 'pending', 'source_fingerprint' => $old]);
        $s->hobi()->sync([Hobi::create(['nama_hobi' => 'sains matematika'])->id]);
        $this->signIn($this->account('administrator'));
        $this->patchJson("/api/rekomendasi-beasiswa/{$draft->id}/review", ['status_review' => 'disetujui'])->assertStatus(409);
        $this->assertDatabaseHas('rekomendasi_beasiswa', ['id' => $draft->id, 'status_review' => 'pending']);
    }

    public function test_empty_catalog_stops_before_ai_and_missing_interests_use_fallback(): void
    {
        [$s] = $this->fixture();
        $this->signIn($this->account('administrator'));
        $s->hobi()->detach();
        $this->getJson("/api/siswa/{$s->id}/rekomendasi-beasiswa/preview")->assertOk()->assertJsonPath('data.pemeringkatan_ml.alasan_status', 'profile_insufficient');
        BasisDataBeasiswa::query()->update(['is_active' => false]);
        $this->getJson("/api/siswa/{$s->id}/rekomendasi-beasiswa/preview")->assertUnprocessable();
        $this->assertDatabaseCount('rekomendasi_beasiswa', 0);
        Http::assertNothingSent();
    }

    public function test_readiness_command_reports_aggregates_without_claiming_effectiveness(): void
    {
        $this->fixture();
        $this->assertSame(0, Artisan::call('ml:beasiswa-check', ['--json' => true]));
        $output = Artisan::output();
        $data = json_decode($output, true, 32, JSON_THROW_ON_ERROR);
        $this->assertSame('ready', $data['status_korpus']);
        $this->assertFalse($data['mutu_rekomendasi_tervalidasi']);
        $this->assertSame(1, $data['profil_dengan_sumber_minat_terstruktur']);
        $this->assertStringNotContainsString('PRIVATE_', $output);
        $this->assertDatabaseCount('rekomendasi_beasiswa', 0);
        Http::assertNothingSent();
    }
    public function test_stored_ml_rank_is_preserved_when_database_returns_unordered_rows(): void
    {
        [$student, $owner, $items] = $this->fixture();
        config(['gemini.api_key' => 'isolated-test-key']);
        Http::fake(['generativelanguage.googleapis.com/*' => function ($request) {
            $input = json_decode($request['input'], true);
            $text = json_encode(['rekomendasi' => array_map(fn ($c) => [
                'beasiswa_id' => $c['beasiswa_id'], 'alasan' => 'Kriteria katalog.',
                'persiapan' => ['Periksa syarat resmi.'],
            ], $input)]);
            return Http::response(['status' => 'completed', 'steps' => [[
                'type' => 'model_output', 'content' => [['type' => 'text', 'text' => $text]],
            ]]]);
        }]);
        $recommendation = app(ScholarshipRecommendationService::class)->generate($student);
        $recommendation->update(['status_review' => 'disetujui']);
        $this->signIn($owner);
        // SQLite's diagnostic mode exposes reliance on accidental row order.
        // TestCase enforces an isolated SQLite :memory: database.
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        DB::statement('PRAGMA reverse_unordered_selects = ON');
        try {
            $data = $this->getJson('/api/rekomendasi-beasiswa/saya')->assertOk()->json('data.0.beasiswa');
            $this->assertSame([$items[1]->id, $items[0]->id], array_column($data, 'beasiswa_id'));
            $this->assertSame([1, 2], array_column(array_column($data, 'pemeringkatan_ml'), 'peringkat'));
        } finally {
            DB::statement('PRAGMA reverse_unordered_selects = OFF');
        }
    }
}

<?php

namespace Tests\Feature;

use App\Models\BasisDataBeasiswa;
use App\Models\RekomendasiBeasiswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\PrdFixtures;
use Tests\TestCase;

class RecommendationConnectionTest extends TestCase
{
    use RefreshDatabase, PrdFixtures;

    private function program(string $name, int $minimum): BasisDataBeasiswa
    {
        $b = BasisDataBeasiswa::create(['nama_beasiswa' => $name, 'jenjang' => 'SMA', 'is_active' => true]);
        $b->kriteriaKelayakanBeasiswa()->create(['nama_kriteria' => 'TKA', 'tipe_kriteria' => 'nilai_tka',
            'operator' => '>=', 'nilai_kriteria' => (string) $minimum]);
        return $b;
    }

    private function provider(array $ids): void
    {
        config(['gemini.api_key' => 'test-key', 'cache.default' => 'array']);
        Http::preventStrayRequests();
        $text = json_encode(['rekomendasi' => array_map(fn ($id) => ['beasiswa_id' => $id,
            'alasan' => 'Periksa hasil kriteria yang tercatat.', 'persiapan' => ['Periksa ketentuan resmi.']], $ids)]);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['status' => 'completed',
            'steps' => [['type' => 'model_output', 'content' => [['type' => 'text', 'text' => $text]]]]])]);
    }

    public function test_picker_preview_generate_review_and_student_feed_use_real_snapshots(): void
    {
        $owner = $this->account();
        $s = $this->student($owner, ['nilai_tka' => 90]); $this->classroom($s);
        $b = $this->program('Program Cukup', 80);
        $unfit = $this->program('Program Tidak Cukup', 99);
        $this->provider([$b->id, $unfit->id]);
        $this->signIn($this->account('administrator'));
        $this->getJson('/api/siswa?search='.$s->nisn)->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson('/api/siswa/'.$s->id.'/rekomendasi-beasiswa/preview?target_jenjang=SMA')
            ->assertOk()->assertJsonCount(2, 'data.kandidat');
        $draft = $this->postJson('/api/siswa/'.$s->id.'/rekomendasi-beasiswa/generate', ['target_jenjang' => 'SMA'])
            ->assertCreated()->json('data.id');
        $this->getJson('/api/rekomendasi-beasiswa?siswa_id='.$s->id.'&target_jenjang=SMA')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $draft);
        $this->getJson('/api/rekomendasi-beasiswa/'.$draft)->assertOk()->assertJsonCount(2, 'data.rekomendasi_beasiswa_detail');
        $this->patchJson('/api/rekomendasi-beasiswa/'.$draft.'/review', ['siswa_id' => $s->id, 'status_review' => 'disetujui',
            'catatan_admin' => 'PRIVATE_NOTE'])->assertOk()->assertJsonPath('data.status_review', 'disetujui')
            ->assertJsonCount(2, 'data.rekomendasi_beasiswa_detail');
        $this->signIn($owner);
        $this->getJson('/api/rekomendasi-beasiswa/saya')->assertOk()->assertJsonCount(1, 'data.0.beasiswa')
            ->assertJsonPath('data.0.beasiswa.0.beasiswa_id', $b->id)->assertJsonStructure(['data' => [['beasiswa' => [['detail_id', 'status_pendaftaran']]]]])
            ->assertDontSee('source_fingerprint')->assertDontSee('PRIVATE_NOTE');
        $this->getJson('/api/rekomendasi-beasiswa/'.$draft)->assertForbidden();
        $b->update(['is_active' => false]);
        $this->getJson('/api/rekomendasi-beasiswa/saya')->assertOk()->assertJsonCount(0, 'data.0.beasiswa');
    }

    public function test_selected_student_cannot_be_swapped_during_publication_and_filters_stay_scoped(): void
    {
        $first = $this->student(null, ['nilai_tka' => 90]); $second = $this->student(null, ['nilai_tka' => 90]);
        $this->classroom($first); $this->classroom($second);
        $b = $this->program('Program Uji', 80); $this->provider([$b->id]);
        $this->signIn($this->account('administrator'));
        $id = $this->postJson('/api/siswa/'.$first->id.'/rekomendasi-beasiswa/generate')->assertCreated()->json('data.id');
        $this->patchJson('/api/rekomendasi-beasiswa/'.$id.'/review', ['siswa_id' => $second->id, 'status_review' => 'disetujui'])->assertNotFound();
        $this->assertDatabaseHas('rekomendasi_beasiswa', ['id' => $id, 'status_review' => 'pending']);
        $this->getJson('/api/rekomendasi-beasiswa?siswa_id='.$second->id)->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/rekomendasi-beasiswa?siswa_id=invalid')->assertUnprocessable();
        $this->getJson('/api/rekomendasi-beasiswa?target_jenjang=invalid')->assertUnprocessable();
    }

    public function test_unknown_tka_produces_an_unpublished_draft_without_fabricating_student_values(): void
    {
        $owner = $this->account(); $s = $this->student($owner); $this->classroom($s);
        $b = $this->program('Program Uji', 80); $this->provider([$b->id]);
        $this->signIn($this->account('administrator'));
        $r = $this->postJson('/api/siswa/'.$s->id.'/rekomendasi-beasiswa/generate')->assertCreated()
            ->assertJsonPath('data.rekomendasi_beasiswa_detail.0.evaluasi_kriteria.lengkap', false)
            ->assertJsonPath('data.rekomendasi_beasiswa_detail.0.evaluasi_kriteria.checklist.0.status', 'belum_diketahui');
        $this->patchJson('/api/rekomendasi-beasiswa/'.$r->json('data.id').'/review', ['status_review' => 'disetujui'])->assertUnprocessable();
        $this->assertNull($s->fresh()->nilai_tka);
        $this->signIn($owner);
        $this->getJson('/api/rekomendasi-beasiswa/saya')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/siswa/'.$s->id.'/rekomendasi-beasiswa/generate')->assertForbidden();
    }

    public function test_other_student_never_reads_or_generates_another_students_recommendations(): void
    {
        $owner = $this->account(); $s = $this->student($owner, ['nilai_tka' => 90]); $this->classroom($s);
        $b = $this->program('Program Uji', 80); $this->provider([$b->id]);
        $this->signIn($this->account('administrator'));
        $id = $this->postJson('/api/siswa/'.$s->id.'/rekomendasi-beasiswa/generate')->assertCreated()->json('data.id');
        $this->patchJson('/api/rekomendasi-beasiswa/'.$id.'/review', ['status_review' => 'disetujui'])->assertOk();
        $other = $this->account(); $this->student($other);
        $this->signIn($other);
        $this->getJson('/api/rekomendasi-beasiswa/saya?siswa_id='.$s->id)->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/rekomendasi-beasiswa/'.$id)->assertForbidden();
        $this->postJson('/api/chat-beasiswa', ['message' => 'Periksa beasiswa saya', 'siswa_id' => $s->id])->assertUnprocessable();
        $this->assertSame(1, RekomendasiBeasiswa::count());
    }
}

<?php

namespace Tests\Feature;

use App\Models\BasisDataBeasiswa;
use App\Models\RekomendasiBeasiswa;
use App\Models\RekomendasiBeasiswaDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\PrdFixtures;
use Tests\TestCase;

class RecommendationCrudTest extends TestCase
{
    use RefreshDatabase, PrdFixtures;

    private function setupDraft(): array
    {
        $owner = $this->account();
        $student = $this->student($owner, ['nilai_tka' => 90]);
        $this->classroom($student);
        $admin = $this->account('administrator');
        $this->signIn($admin);
        $ids = [];
        foreach (['Program Satu', 'Program Dua'] as $name) {
            $ids[] = $this->postJson('/api/beasiswa', ['nama_beasiswa' => $name, 'jenjang' => 'SMA',
                'kriteria' => [['nama_kriteria' => 'TKA', 'tipe_kriteria' => 'nilai_tka', 'operator' => '>=', 'nilai_kriteria' => '80']]])
                ->assertCreated()->json('data.id');
        }
        config(['gemini.api_key' => 'test-key', 'cache.default' => 'array']);
        Http::preventStrayRequests();
        $answer = json_encode(['rekomendasi' => array_map(fn ($id) => ['beasiswa_id' => $id,
            'alasan' => 'Memenuhi kriteria tercatat.', 'persiapan' => ['Periksa jadwal resmi.']], $ids)]);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['status' => 'completed',
            'steps' => [['type' => 'model_output', 'content' => [['type' => 'text', 'text' => $answer]]]]])]);
        $draft = $this->postJson('/api/siswa/'.$student->id.'/rekomendasi-beasiswa/generate', ['target_jenjang' => 'SMA'])
            ->assertCreated()->json('data');
        return [$owner, $student, $admin, $draft, $ids];
    }

    public function test_recommendation_crud_is_persisted_and_synchronizes_only_after_review(): void
    {
        [$owner, $student, $admin, $draft, $ids] = $this->setupDraft();
        $id = $draft['id']; $detail = $draft['rekomendasi_beasiswa_detail'][0]['id'];
        $otherDetail = $draft['rekomendasi_beasiswa_detail'][1]['id'];
        $url = "/api/rekomendasi-beasiswa/$id/detail/$detail";
        $publish = function (int $revision) use ($id, $student) {
            $this->patchJson("/api/rekomendasi-beasiswa/$id/review", ['siswa_id' => $student->id,
                'revision' => $revision, 'status_review' => 'disetujui'])->assertOk();
        };
        $publish(1);
        $this->signIn($owner);
        $this->getJson('/api/rekomendasi-beasiswa/saya')->assertOk()->assertJsonCount(2, 'data.0.beasiswa');
        $this->signIn($admin);
        $this->patchJson($url, ['siswa_id' => $student->id, 'revision' => 2,
            'alasan_rekomendasi' => 'Alasan ditinjau sekolah.', 'waktu_disarankan' => 'Januari 2027'])
            ->assertOk()->assertJsonPath('data.revision', 3)->assertJsonPath('data.status_review', 'pending')
            ->assertJsonPath('data.rekomendasi_beasiswa_detail.0.alasan_rekomendasi', 'Alasan ditinjau sekolah.');
        $this->signIn($owner);
        $this->getJson('/api/rekomendasi-beasiswa/saya')->assertOk()->assertJsonCount(0, 'data');
        $this->signIn($admin); $publish(3);
        $this->signIn($owner);
        $this->getJson('/api/rekomendasi-beasiswa/saya')->assertOk()
            ->assertJsonPath('data.0.beasiswa.0.alasan', 'Alasan ditinjau sekolah.')
            ->assertJsonPath('data.0.beasiswa.0.waktu_disarankan', 'Januari 2027')
            ->assertDontSee('source_fingerprint')->assertDontSee('reviewed_by');
        $this->signIn($admin);
        $this->patchJson($url, ['siswa_id' => $student->id, 'revision' => 4, 'is_hidden' => true])->assertOk();
        $publish(5);
        $this->signIn($owner);
        $this->getJson('/api/rekomendasi-beasiswa/saya')->assertOk()->assertJsonCount(1, 'data.0.beasiswa')
            ->assertJsonPath('data.0.beasiswa.0.beasiswa_id', $ids[1]);
        $this->signIn($admin);
        $this->patchJson($url, ['siswa_id' => $student->id, 'revision' => 6, 'is_hidden' => false])->assertOk();
        $publish(7);
        $this->signIn($owner);
        $this->getJson('/api/rekomendasi-beasiswa/saya')->assertOk()->assertJsonCount(2, 'data.0.beasiswa');
        $this->signIn($admin);
        $this->deleteJson($url, ['siswa_id' => $student->id, 'revision' => 8])->assertOk()->assertJsonCount(1, 'data.rekomendasi_beasiswa_detail');
        $this->assertSoftDeleted('rekomendasi_beasiswa_detail', ['id' => $detail]);
        $publish(9);
        $this->signIn($owner);
        $this->getJson('/api/rekomendasi-beasiswa/saya')->assertOk()->assertJsonCount(1, 'data.0.beasiswa')
            ->assertJsonPath('data.0.beasiswa.0.beasiswa_id', $ids[1]);
        $this->signIn($admin);
        $this->deleteJson("/api/rekomendasi-beasiswa/$id/detail/$otherDetail", ['siswa_id' => $student->id, 'revision' => 10])->assertOk();
        $this->patchJson("/api/rekomendasi-beasiswa/$id/review", ['revision' => 11, 'status_review' => 'disetujui'])->assertUnprocessable();
        $this->assertSame(0, RekomendasiBeasiswaDetail::count());
        $this->assertSame(2, RekomendasiBeasiswaDetail::withTrashed()->count());
    }

    public function test_editing_is_scoped_rejects_stale_or_immutable_values_and_checks_student_role(): void
    {
        [$owner, $student, $admin, $draft] = $this->setupDraft();
        $id = $draft['id']; $detail = $draft['rekomendasi_beasiswa_detail'][0]['id'];
        $url = "/api/rekomendasi-beasiswa/$id/detail/$detail";
        $other = $this->student();
        $body = ['siswa_id' => $student->id, 'revision' => 1, 'alasan_rekomendasi' => 'Perbaikan sekolah.'];
        $this->patchJson($url, [...$body, 'siswa_id' => $other->id])->assertNotFound();
        $this->patchJson($url, [...$body, 'revision' => 99])->assertConflict();
        $this->patchJson($url, [...$body, 'skor_kecocokan' => 100])->assertUnprocessable();
        $this->patchJson($url, [...$body, 'alasan_rekomendasi' => '<b></b>'])->assertUnprocessable();
        $this->patchJson($url, [...$body, 'alasan_rekomendasi' => str_repeat('x', 1501)])->assertUnprocessable();
        $this->patchJson($url, [...$body, 'is_hidden' => 'yes'])->assertUnprocessable();
        $otherBatch = RekomendasiBeasiswa::create(['siswa_id' => $other->id, 'target_jenjang' => 'SMA',
            'tanggal_rekomendasi' => today(), 'status_review' => 'pending']);
        $cross = $otherBatch->rekomendasiBeasiswaDetail()->create(['beasiswa_id' => $draft['rekomendasi_beasiswa_detail'][0]['beasiswa_id'], 'skor_kecocokan' => 0]);
        $this->patchJson("/api/rekomendasi-beasiswa/$id/detail/$cross->id", $body)->assertNotFound();
        $this->patchJson($url, $body)->assertOk();
        $this->patchJson($url, $body)->assertConflict();
        $this->patchJson("/api/rekomendasi-beasiswa/$id/review", ['revision' => 1, 'status_review' => 'disetujui'])->assertConflict();
        $this->signIn($owner);
        $this->patchJson($url, [...$body, 'revision' => 2])->assertForbidden();
        $this->deleteJson($url, ['siswa_id' => $student->id, 'revision' => 2])->assertForbidden();
    }

    public function test_newer_draft_retracts_old_publication_and_old_batch_cannot_be_republished(): void
    {
        [$owner, $student, $admin, $draft] = $this->setupDraft();
        $id = $draft['id']; $detail = $draft['rekomendasi_beasiswa_detail'][0]['id'];
        $this->patchJson("/api/rekomendasi-beasiswa/$id/review", ['revision' => 1, 'status_review' => 'disetujui'])->assertOk();
        $new = $this->postJson('/api/siswa/'.$student->id.'/rekomendasi-beasiswa/generate', ['target_jenjang' => 'SMA'])->assertCreated()->json('data.id');
        $this->getJson('/api/siswa?rekomendasi_published=1')->assertOk()->assertJsonPath('data.total', 0);
        $this->patchJson("/api/rekomendasi-beasiswa/$id/detail/$detail", ['siswa_id' => $student->id, 'revision' => 2, 'is_hidden' => true])->assertConflict();
        $this->patchJson("/api/rekomendasi-beasiswa/$id/review", ['revision' => 2, 'status_review' => 'disetujui'])->assertConflict();
        $this->signIn($owner);
        $this->getJson('/api/rekomendasi-beasiswa/saya')->assertOk()->assertJsonCount(0, 'data');
        $this->signIn($admin);
        $this->patchJson("/api/rekomendasi-beasiswa/$new/review", ['revision' => 1, 'status_review' => 'disetujui'])->assertOk();
        $this->signIn($owner);
        $this->getJson('/api/rekomendasi-beasiswa/saya')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $new);
        $stranger = $this->account(); $this->student($stranger); $this->signIn($stranger);
        $this->getJson('/api/rekomendasi-beasiswa/saya?siswa_id='.$student->id)->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_catalog_crud_and_archival_are_real_and_keep_recommendation_history(): void
    {
        [$owner, $student, $admin, $draft, $ids] = $this->setupDraft();
        $data = $this->getJson('/api/beasiswa/'.$ids[0])->assertOk()->json('data');
        $input = ['nama_beasiswa' => 'Nama Diperbarui', 'jenjang' => 'SMA', 'kriteria' => array_map(fn ($c) => array_intersect_key($c,
            array_flip(['nama_kriteria', 'tipe_kriteria', 'operator', 'nilai_kriteria', 'bobot'])), $data['kriteria'])];
        $this->putJson('/api/beasiswa/'.$ids[0], $input)->assertOk()->assertJsonPath('data.nama_beasiswa', 'Nama Diperbarui');
        $this->deleteJson('/api/beasiswa/'.$ids[0])->assertOk();
        $this->getJson('/api/beasiswa/'.$ids[0])->assertOk()->assertJsonPath('data.is_active', false);
        $this->putJson('/api/beasiswa/'.$ids[0], $input + ['is_active' => true])->assertOk()->assertJsonPath('data.is_active', true);
        $this->assertDatabaseCount('basis_data_beasiswa', 2);
        $this->assertDatabaseCount('rekomendasi_beasiswa_detail', 2);
        $this->signIn($owner);
        $this->putJson('/api/beasiswa/'.$ids[0], $input)->assertForbidden();
        $this->deleteJson('/api/beasiswa/'.$ids[0])->assertForbidden();
    }
}

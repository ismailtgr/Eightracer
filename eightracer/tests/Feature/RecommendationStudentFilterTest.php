<?php

namespace Tests\Feature;

use App\Models\RekomendasiBeasiswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PrdFixtures;
use Tests\TestCase;

class RecommendationStudentFilterTest extends TestCase
{
    use RefreshDatabase, PrdFixtures;

    public function test_recommendation_filters_use_saved_publication_classroom_and_cohort_data(): void
    {
        $published = $this->student(null, ['tahun_lulus' => 2026]);
        $unpublished = $this->student(null, ['tahun_lulus' => 2025]);
        $this->classroom($published); $this->classroom($unpublished);
        $class = $published->riwayatKelas()->first()->kelas_id;
        RekomendasiBeasiswa::create(['siswa_id' => $published->id, 'target_jenjang' => 'Kuliah',
            'tanggal_rekomendasi' => today(), 'status_review' => 'disetujui']);
        $this->signIn($this->account('administrator'));
        $this->getJson('/api/siswa?rekomendasi_published=1')->assertOk()
            ->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.id', $published->id)
            ->assertJsonCount(2, 'filters.cohorts')->assertJsonCount(2, 'filters.classrooms');
        $this->getJson('/api/siswa?rekomendasi_published=0')->assertOk()
            ->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.id', $unpublished->id);
        $this->getJson('/api/siswa?kelas_id='.$class.'&angkatan=2026')->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson('/api/siswa?rekomendasi_published=invalid')->assertUnprocessable();
        $this->signIn($this->account());
        $this->getJson('/api/siswa?rekomendasi_published=1')->assertForbidden();
    }
}

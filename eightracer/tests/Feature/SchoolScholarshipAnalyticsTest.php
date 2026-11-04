<?php

namespace Tests\Feature;

use App\Models\BasisDataBeasiswa;
use App\Models\SchoolScholarshipObservation;
use App\Services\School\SchoolScholarshipAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Concerns\PrdFixtures;
use Tests\TestCase;

class SchoolScholarshipAnalyticsTest extends TestCase
{
    use RefreshDatabase, PrdFixtures;

    private function source($student, $admission, string $column, string $value): void
    {
        SchoolScholarshipObservation::create([
            'source_key' => hash('sha256', uniqid('', true)), 'source_file_id' => 'school-test',
            'source_sha256' => str_repeat('a', 64), 'source_sheet' => 'SNBT',
            'source_row' => random_int(1, 100000), 'source_column' => 'G', 'tahun_lulus' => 2026,
            'siswa_id' => $student?->id, 'study_tracer_id' => $admission?->id,
            'nama_kolom' => $column, 'nilai_sumber' => $value, 'jenis_keterangan' => 'referensi_beasiswa',
        ]);
    }

    public function test_school_marks_and_named_awards_are_unique_students_without_invented_catalog_or_finances(): void
    {
        $s = $this->student(null, ['tahun_lulus' => 2026]); $t = $this->admission($s);
        $other = $this->student(null, ['tahun_lulus' => 2026]); $a = $this->admission($other);
        $this->student(null, ['tahun_lulus' => 2025]);
        $this->source($s, $t, 'KIPK', 'V');
        $this->source($s, $t, 'KIP', 'YA');
        $this->source($other, $a, 'BEASISWA', 'BEASISWA TELADAN');
        $this->source($other, $a, 'BEASISWA', 'BEASISWA TELADAN');
        foreach (['TPKS', 'BEBAS IPI', 'Retsumeikan Asia Pacific University', 'Tidak menerima beasiswa'] as $value) {
            $this->source($other, $a, 'BEASISWA', $value);
        }
        $this->source(null, null, 'KIPK', 'YA');
        $this->signIn($this->account('administrator'));
        $r = $this->getJson('/api/analitik-beasiswa/sekolah')->assertOk();
        $r->assertJsonPath('data.summary.alumni', 3)->assertJsonPath('data.summary.admitted_alumni', 2)
            ->assertJsonPath('data.summary.kip', 1)->assertJsonPath('data.summary.all_aid', 2)
            ->assertJsonPath('data.summary.scholarship_non_kip', 1)->assertJsonPath('data.summary.source_unlinked', 1)
            ->assertJsonPath('data.financial.ukt.minimum', null)->assertJsonPath('data.financial.ukt.known', 0)
            ->assertJsonPath('data.financial.ukt.unknown', 3)->assertJsonPath('data.financial.ipk.mean', null)
            ->assertJsonCount(2, 'data.financial.ranges')->assertJsonCount(0, 'data.periods')
            ->assertDontSee('PRIVATE_PHONE')->assertDontSee('nama_lengkap');
        $this->assertDatabaseCount('basis_data_beasiswa', 0);
        $this->assertDatabaseCount('alumni_progress', 0);
        $this->assertDatabaseCount('school_scholarship_observations', 9);
    }

    public function test_campus_filter_does_not_assign_award_to_a_different_admission_and_periods_are_not_guessed(): void
    {
        $s = $this->student(null, ['tahun_lulus' => 2026]);
        $first = $this->admission($s); $second = $this->admission($s);
        $second->update(['program_studi' => 'Ekonomi']);
        $this->source($s, $first, 'KIPK', 'V');
        $this->signIn($this->account('administrator'));
        $this->getJson('/api/analitik-beasiswa/sekolah?perguruan_tinggi_id='.$second->perguruan_tinggi_id)
            ->assertOk()->assertJsonPath('data.summary.alumni', 1)->assertJsonPath('data.summary.kip', 0);
        $this->getJson('/api/analitik-beasiswa/sekolah?prodi=info')->assertOk()->assertJsonPath('data.summary.kip', 1);
        $this->getJson('/api/analitik-beasiswa/sekolah?periode=2026')->assertOk()->assertJsonPath('data.summary.alumni', 0)
            ->assertJsonPath('data.summary.kip', 0)->assertJsonCount(0, 'data.periods');
        $this->getJson('/api/analitik-beasiswa/sekolah?beasiswa_id=kip')->assertOk()->assertJsonPath('data.summary.kip', 1);
        $this->getJson('/api/analitik-beasiswa/sekolah?beasiswa_id=invalid')->assertUnprocessable();
        $this->getJson('/api/analitik-beasiswa/sekolah?angkatan=abc')->assertUnprocessable();
    }

    public function test_latest_progress_does_not_double_count_students_and_zero_ukt_is_known(): void
    {
        $s = $this->student(null, ['tahun_lulus' => 2026]); $t = $this->admission($s);
        $award = BasisDataBeasiswa::create(['nama_beasiswa' => 'Program Sekolah', 'jenjang' => 'Kuliah', 'is_active' => true]);
        $this->signIn($this->account('administrator'));
        $body = ['study_tracer_id' => $t->id, 'semester' => 1, 'periode' => 'Semester 1',
            'status_pendidikan' => 'aktif', 'tanggal_update' => today()->subDay()->toDateString(),
            'ipk_terkini' => 3, 'nominal_ukt' => 1000000, 'penerima_kipk' => true,
            'beasiswa' => [['beasiswa_id' => $award->id, 'status_beasiswa' => 'diterima']]];
        $this->postJson('/api/alumni/'.$s->id.'/progress', $body)->assertCreated();
        $this->postJson('/api/alumni/'.$s->id.'/progress', [...$body, 'semester' => 2, 'periode' => 'Semester 2',
            'tanggal_update' => today()->toDateString(), 'ipk_terkini' => 3.5, 'nominal_ukt' => 0, 'penerima_kipk' => false])->assertCreated();
        $this->getJson('/api/analitik-beasiswa/sekolah')->assertOk()->assertJsonPath('data.summary.all_aid', 1)
            ->assertJsonPath('data.summary.kip', 0)->assertJsonPath('data.financial.measured_alumni', 1)
            ->assertJsonPath('data.financial.ukt.minimum', 0)->assertJsonPath('data.financial.ukt.known', 1)
            ->assertJsonPath('data.financial.ipk.mean', 3.5)->assertJsonCount(2, 'data.periods');
        $this->getJson('/api/analitik-beasiswa/sekolah?periode=Semester%201')->assertOk()->assertJsonPath('data.summary.kip', 1)
            ->assertJsonPath('data.financial.ipk.mean', 3);
        $this->getJson('/api/analitik-beasiswa/sekolah?beasiswa_id='.$award->id)->assertOk()->assertJsonPath('data.summary.all_aid', 1);
    }

    public function test_student_and_unverified_accounts_cannot_read_staff_analytics_or_pdf(): void
    {
        $this->getJson('/api/analitik-beasiswa/sekolah')->assertUnauthorized();
        $this->signIn($this->account());
        $this->getJson('/api/analitik-beasiswa/sekolah')->assertForbidden();
        $this->getJson('/api/analitik-beasiswa/sekolah/pdf')->assertForbidden();
        $this->signIn($this->account('administrator', ['email_verified_at' => null]));
        $this->getJson('/api/analitik-beasiswa/sekolah')->assertForbidden();
    }

    public function test_catalog_detail_exposes_the_existing_criteria_contract_and_pdf_failure_is_generic(): void
    {
        $b = BasisDataBeasiswa::create(['nama_beasiswa' => 'Program Sekolah', 'jenjang' => 'Kuliah',
            'periode_apply' => 'September - Oktober', 'is_active' => true]);
        $b->kriteriaKelayakanBeasiswa()->create(['nama_kriteria' => 'Nilai rapor', 'tipe_kriteria' => 'rata_rata_rapor', 'operator' => '>=', 'nilai_kriteria' => '85']);
        $this->signIn($this->account('administrator'));
        $this->getJson('/api/beasiswa/'.$b->id)->assertOk()->assertJsonCount(1, 'data.kriteria')
            ->assertJsonPath('data.kriteria.0.nilai_kriteria', '85')->assertJsonPath('data.status_pendaftaran', 'belum_ditentukan');
        $this->getJson('/api/beasiswa')->assertOk()->assertJsonCount(1, 'data.0.kriteria');
        config(['b4.python_binary' => 'invalid-internal-binary']);
        $this->getJson('/api/analitik-beasiswa/sekolah/pdf')->assertStatus(503)->assertDontSee('invalid-internal-binary');
    }
}

<?php

namespace Tests\Feature;

use App\Models\AlumniProgress;
use App\Models\StudyTracer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PrdFixtures;
use Tests\TestCase;

class AlumniDirectoryTest extends TestCase
{
    use PrdFixtures, RefreshDatabase;

    public function test_directory_uses_real_admission_rows_without_private_fields_or_invented_progress(): void
    {
        $this->withoutExceptionHandling();
        $student = $this->student(null, ['nama_lengkap' => 'Alumni Satu', 'tahun_lulus' => 2024, 'catatan' => 'PRIVATE_SOURCE']);
        $record = $this->admission($student, 'SNBP');
        $other = $this->admission($this->student(null, ['nama_lengkap' => 'Alumni Dua', 'tahun_lulus' => 2025]), 'SNBT');
        $this->signIn($this->account('administrator'));
        $page = $this->getJson('/api/alumni?include_filters=1')->assertOk()
            ->assertJsonPath('total', 2)->assertJsonPath('per_page', 8)
            ->assertJsonPath('data.0.status_terakhir', null)->assertJsonCount(2, 'filters.universities');
        $this->assertStringNotContainsString('PRIVATE_SOURCE', $page->getContent());
        $this->assertStringNotContainsString('PRIVATE_PHONE', $page->getContent());
        $page->assertJsonMissingPath('data.0.siswa')->assertJsonMissingPath('data.0.kontak_wa_terbaru');
        $this->getJson('/api/alumni?search=Alumni%20Satu')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $record->id);
        $this->getJson('/api/alumni?perguruan_tinggi_id='.$other->perguruan_tinggi_id.'&jalur_masuk=SNBT&program_studi=Informatika&tahun_lulus=2025')
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $other->id);
        $this->getJson('/api/alumni?search=tidak-ada')->assertOk()->assertJsonPath('total', 0)->assertJsonCount(0, 'data');
    }

    public function test_detail_matches_the_admission_id_and_preserves_unknown_data(): void
    {
        $student = $this->student(null, ['tahun_lulus' => 2024]);
        $record = $this->admission($student);
        $record->forceFill(['nilai_rapor_pendaftaran' => null, 'skor_utbk' => null, 'tahun_masuk_pt' => null])->save();
        $this->classroom($student);
        $this->signIn($this->account('administrator'));
        $response = $this->getJson('/api/alumni/'.$record->id)->assertOk()->assertJsonPath('data.id', $record->id)
            ->assertJsonPath('data.nama_lengkap', 'Murid Uji')->assertJsonPath('data.nilai_rapor_pendaftaran', null)
            ->assertJsonPath('data.skor_utbk', null)->assertJsonPath('data.tahun_masuk_pt', null)
            ->assertJsonPath('data.status_terakhir', null)->assertJsonCount(0, 'data.progress')
            ->assertJsonMissingPath('data.nisn')->assertJsonMissingPath('data.kontak_wa_terbaru')
            ->assertJsonMissingPath('data.siswa')->assertJsonMissingPath('data.catatan');
        $this->assertNotEmpty($response->json('data.kelas_terakhir'));
        $this->assertStringNotContainsString('PRIVATE_PHONE', $response->getContent());
        $this->getJson('/api/alumni/999999')->assertNotFound();
    }

    public function test_two_acceptances_for_one_student_keep_separate_progress_and_detail_links(): void
    {
        $student = $this->student(null, ['tahun_lulus' => 2024]);
        $first = $this->admission($student);
        $second = StudyTracer::create(['siswa_id' => $student->id, 'perguruan_tinggi_id' => $first->perguruan_tinggi_id,
            'strata_pendidikan' => 'S1', 'program_studi' => 'Fisika', 'jalur_masuk' => 'SNBT',
            'status_penerimaan' => 'diterima', 'tahun_masuk_pt' => 2026]);
        AlumniProgress::create(['siswa_id' => $student->id, 'study_tracer_id' => $first->id,
            'periode' => 'Ganjil 2026/2027', 'semester' => 1, 'tahun_lulus' => 2024,
            'tanggal_update' => '2026-11-04', 'status_pendidikan' => 'aktif', 'ipk_terkini' => 3.5,
            'penerima_kipk' => false, 'nominal_ukt' => 0, 'nominal_ipi' => null, 'is_current' => true]);
        $this->signIn($this->account('administrator'));
        $this->getJson('/api/alumni')->assertOk()->assertJsonPath('total', 2);
        $this->getJson('/api/alumni/'.$first->id)->assertOk()->assertJsonCount(1, 'data.progress')
            ->assertJsonPath('data.status_terakhir', 'aktif')->assertJsonPath('data.progress.0.penerima_kipk', false);
        $this->getJson('/api/alumni/'.$second->id)->assertOk()->assertJsonCount(0, 'data.progress')->assertJsonPath('data.status_terakhir', null);
    }

    public function test_directory_paginates_and_rejects_invalid_filters(): void
    {
        $student = $this->student();
        $record = $this->admission($student);
        for ($i = 1; $i < 9; $i++) {
            StudyTracer::create(['siswa_id' => $student->id, 'perguruan_tinggi_id' => $record->perguruan_tinggi_id,
                'strata_pendidikan' => 'S1', 'program_studi' => 'Program '.$i, 'jalur_masuk' => 'Mandiri',
                'status_penerimaan' => 'diterima']);
        }
        $this->signIn($this->account('administrator'));
        $this->getJson('/api/alumni?page=2')->assertOk()->assertJsonPath('total', 9)->assertJsonPath('current_page', 2)->assertJsonCount(1, 'data');
        foreach (['page=0', 'page=invalid', 'per_page=101', 'perguruan_tinggi_id=-1', 'tahun_lulus=abcd', 'include_filters=invalid'] as $query) {
            $this->getJson('/api/alumni?'.$query)->assertUnprocessable();
        }
    }

    public function test_school_directory_is_staff_only_and_does_not_include_rejected_applications(): void
    {
        $record = $this->admission($this->student());
        $this->getJson('/api/alumni')->assertUnauthorized();
        $this->signIn($this->account());
        $this->getJson('/api/alumni')->assertForbidden();
        $this->getJson('/api/alumni/'.$record->id)->assertForbidden();
        $this->signIn($this->account('administrator'));
        $record->forceFill(['status_penerimaan' => 'tidak_diterima'])->save();
        $this->getJson('/api/alumni?include_filters=1')->assertOk()->assertJsonPath('total', 0)->assertJsonCount(0, 'filters.universities');
        $this->getJson('/api/alumni/'.$record->id)->assertNotFound();
    }

    public function test_source_categories_replace_lainnya_without_confusing_another_acceptance_or_guessing(): void
    {
        $student = $this->student(null, ['tahun_lulus' => 2024, 'catatan' => json_encode([
            'kelas_sumber' => ['XII-2'], 'data_sumber' => [
                ['kind' => 'admission', 'source' => ['sheet' => 'SWASTA', 'year' => 2024], 'degree' => 'S1', 'program_original' => 'S1 Informatika', 'route_original' => 'Tes'],
                ['kind' => 'admission', 'source' => ['sheet' => 'UM PTKIN', 'year' => 2024], 'degree' => 'S1', 'program_original' => 'S1 Fisika'],
            ]])]);
        $private = $this->admission($student, 'Lainnya');
        $other = $this->admission($this->student(null, ['tahun_lulus' => 2024]), 'Lainnya');
        $named = $this->admission($student, 'SNBT');
        $this->signIn($this->account('administrator'));
        $this->getJson('/api/alumni/'.$private->id)->assertOk()->assertJsonPath('data.jalur_masuk', 'Jalur Swasta')->assertJsonPath('data.kelas_terakhir', 'XII-2');
        $this->getJson('/api/alumni/'.$other->id)->assertOk()->assertJsonPath('data.jalur_masuk', null);
        $this->getJson('/api/alumni/'.$named->id)->assertOk()->assertJsonPath('data.jalur_masuk', 'SNBT');
        $page = $this->getJson('/api/alumni?include_filters=1')->assertOk()->json();
        $this->assertEqualsCanonicalizing(['SNBT', 'Jalur Swasta'], $page['filters']['admission_paths']);
        $this->getJson('/api/alumni?jalur_masuk=Jalur%20Swasta')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $private->id);
        $this->getJson('/api/alumni?jalur_masuk=SNBT')->assertOk()->assertJsonPath('total', 1);
    }

    public function test_adik_um_ptkin_and_overseas_use_the_school_source_category(): void
    {
        $this->signIn($this->account('administrator'));
        foreach (['ADIK' => 'ADIK', 'UM PTKIN' => 'UM PTKIN', 'LUAR NEGERI' => 'Luar Negeri'] as $sheet => $label) {
            $student = $this->student(null, ['tahun_lulus' => 2025, 'catatan' => json_encode(['data_sumber' => [
                ['kind' => 'admission', 'source' => ['sheet' => $sheet, 'year' => 2025], 'degree' => 'S1', 'program_original' => 'S1 Informatika'],
            ]])]);
            $record = $this->admission($student, 'Lainnya');
            $this->getJson('/api/alumni/'.$record->id)->assertOk()->assertJsonPath('data.jalur_masuk', $label);
            $this->getJson('/api/alumni?jalur_masuk='.rawurlencode($label))->assertOk()->assertJsonPath('total', 1);
        }
    }

}

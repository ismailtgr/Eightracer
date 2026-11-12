<?php

namespace Tests\Feature;

use App\Models\AlumniProgress;
use App\Models\StudyTracer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PrdFixtures;
use Tests\TestCase;

class AlumniTimelineTest extends TestCase
{
    use PrdFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        $this->travelTo(\Carbon\Carbon::parse('2026-11-04 12:00:00', 'Asia/Jakarta'));
    }

    private function complete(array $studentFields = [], array $admissionFields = [], array $progressFields = []): StudyTracer
    {
        $s = $this->student(null, [...['tahun_lulus' => 2024, 'tanggal_masuk_sekolah' => '2021-07-12', 'tanggal_lulus' => '2024-05-06'], ...$studentFields]);
        $t = $this->admission($s);
        $t->forceFill([...['tahun_masuk_pt' => 2024, 'tanggal_masuk_pt' => '2024-08-01'], ...$admissionFields])->save();
        AlumniProgress::create([...['siswa_id' => $s->id, 'study_tracer_id' => $t->id, 'periode' => 'Ganjil 2024/2025',
            'semester' => 1, 'tahun_lulus' => 2024, 'tanggal_update' => '2024-12-20', 'status_pendidikan' => 'aktif',
            'ipk_terkini' => 3.5, 'penerima_kipk' => false, 'nominal_ukt' => 0, 'is_current' => true, 'revision' => 1], ...$progressFields]);
        return $t;
    }

    public function test_only_complete_alumni_and_their_filter_options_are_returned(): void
    {
        $complete = $this->complete(['nisn' => null]);
        $this->complete(['tanggal_masuk_sekolah' => null]);
        $this->complete([], ['tahun_masuk_pt' => null, 'tanggal_masuk_pt' => null]);
        foreach (['ipk_terkini', 'nominal_ukt', 'penerima_kipk'] as $field) $this->complete([], [], [$field => null]);
        $this->signIn($this->account('administrator'));
        $this->getJson('/api/b4/alumni?timeline_complete=1&include_filters=1')->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('timeline_complete_only', true)
            ->assertJsonPath('data.0.id', $complete->id)->assertJsonCount(1, 'filters.universities');
        $this->getJson('/api/b4/alumni')->assertOk()->assertJsonPath('total', 6);
        $detail = $this->getJson('/api/b4/alumni/'.$complete->id.'?timeline_complete=1')->assertOk()
            ->assertJsonPath('data.timeline_complete', true)->assertJsonPath('data.has_nisn', false)
            ->assertJsonCount(3, 'data.education_events')->assertJsonPath('data.education_events.0.date', '2021-07-12')
            ->assertJsonPath('data.progress.0.penerima_kipk', false);
        $this->assertEquals(0, $detail->json('data.progress.0.nominal_ukt'));
        foreach (['nisn','catatan','kontak_wa_terbaru','user','siswa'] as $field) $detail->assertJsonMissingPath('data.'.$field);
    }

    public function test_incomplete_detail_cannot_be_opened_by_guessing_a_complete_timeline_id(): void
    {
        $record = $this->admission($this->student(null, ['tahun_lulus' => 2024]));
        $this->signIn($this->account('administrator'));
        $this->getJson('/api/b4/alumni?timeline_complete=1&include_filters=1')->assertOk()
            ->assertJsonPath('total', 0)->assertJsonCount(0, 'filters.universities')->assertJsonCount(0, 'filters.admission_paths');
        $this->getJson('/api/b4/alumni/'.$record->id.'?timeline_complete=1')->assertNotFound();
        $this->getJson('/api/b4/alumni/'.$record->id)->assertOk()->assertJsonPath('data.timeline_complete', false);
    }

    public function test_latest_current_period_must_be_complete_not_just_an_older_period(): void
    {
        $record = $this->complete();
        $latest = AlumniProgress::create(['siswa_id' => $record->siswa_id, 'study_tracer_id' => $record->id,
            'periode' => 'Genap 2024/2025', 'semester' => 2, 'tahun_lulus' => 2024, 'tanggal_update' => '2025-06-01',
            'status_pendidikan' => 'aktif', 'ipk_terkini' => null, 'penerima_kipk' => false, 'nominal_ukt' => 0, 'is_current' => true]);
        $this->signIn($this->account('administrator'));
        $this->getJson('/api/b4/alumni?timeline_complete=1')->assertOk()->assertJsonPath('total', 0);
        $latest->update(['ipk_terkini' => 0]);
        $this->getJson('/api/b4/alumni?timeline_complete=1')->assertOk()->assertJsonPath('total', 1);
        $latest->update(['is_current' => false]);
        $this->getJson('/api/b4/alumni/'.$record->id.'?timeline_complete=1')->assertOk()->assertJsonCount(1, 'data.progress');
    }

    public function test_same_day_latest_id_is_used_and_two_admissions_never_mix(): void
    {
        $record = $this->complete();
        $other = StudyTracer::create(['siswa_id' => $record->siswa_id, 'perguruan_tinggi_id' => $record->perguruan_tinggi_id,
            'program_studi' => 'Fisika', 'strata_pendidikan' => 'S1', 'jalur_masuk' => 'Mandiri', 'status_penerimaan' => 'diterima',
            'tahun_masuk_pt' => 2024, 'tanggal_masuk_pt' => '2024-08-01']);
        $this->signIn($this->account('administrator'));
        $this->getJson('/api/b4/alumni?timeline_complete=1')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $record->id);
        $this->getJson('/api/b4/alumni/'.$other->id.'?timeline_complete=1')->assertNotFound();
        AlumniProgress::create(['siswa_id' => $record->siswa_id, 'study_tracer_id' => $record->id,
            'periode' => 'Koreksi Terkini', 'semester' => 2, 'tahun_lulus' => 2024, 'tanggal_update' => '2024-12-20',
            'status_pendidikan' => 'aktif', 'ipk_terkini' => 3.6, 'penerima_kipk' => null, 'nominal_ukt' => 0, 'is_current' => true]);
        $this->getJson('/api/b4/alumni?timeline_complete=1')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_year_precision_is_preserved_without_fabricating_month_or_grades(): void
    {
        $record = $this->complete(['tanggal_lulus' => null], ['tanggal_masuk_pt' => null]);
        $this->signIn($this->account('administrator'));
        $this->getJson('/api/b4/alumni/'.$record->id.'?timeline_complete=1')->assertOk()
            ->assertJsonPath('data.education_events.1.date', null)->assertJsonPath('data.education_events.1.year', 2024)
            ->assertJsonPath('data.education_events.2.date', null)->assertJsonPath('data.education_events.2.year', 2024)
            ->assertJsonPath('data.nilai_rapor_pendaftaran', null)->assertJsonPath('data.skor_utbk', null);
    }

    public function test_impossible_or_future_chronology_is_held(): void
    {
        $this->complete(['tanggal_masuk_sekolah' => '2025-07-01']);
        $this->complete([], ['tahun_masuk_pt' => 2023, 'tanggal_masuk_pt' => '2023-08-01']);
        $this->complete([], ['tahun_masuk_pt' => 2027, 'tanggal_masuk_pt' => '2027-08-01']);
        $this->complete([], [], ['tanggal_update' => '2027-01-01']);
        $this->complete([], ['tanggal_masuk_pt' => '2024-08-01'], ['tanggal_update' => '2024-07-01']);
        $this->complete([], ['tanggal_masuk_pt' => null], ['tanggal_update' => '2024-01-01']);
        $this->signIn($this->account('administrator'));
        $this->getJson('/api/b4/alumni?timeline_complete=1')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_timeline_mode_is_staff_only_validated_and_supports_existing_filters(): void
    {
        $record = $this->complete(['nama_lengkap' => 'Alumni Lengkap Fixture']);
        $this->getJson('/api/b4/alumni?timeline_complete=1')->assertUnauthorized();
        $this->signIn($this->account());
        $this->getJson('/api/b4/alumni?timeline_complete=1')->assertForbidden();
        $this->getJson('/api/b4/alumni/'.$record->id.'?timeline_complete=1')->assertForbidden();
        $this->signIn($this->account('administrator'));
        $this->getJson('/api/b4/alumni?timeline_complete=wrong')->assertUnprocessable();
        $this->getJson('/api/b4/alumni/'.$record->id.'?timeline_complete=wrong')->assertUnprocessable();
        $this->getJson('/api/b4/alumni?timeline_complete=1&search=Lengkap&jalur_masuk=Mandiri')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/b4/alumni?timeline_complete=1&program_studi=TidakAda')->assertOk()->assertJsonPath('total', 0);
    }
}

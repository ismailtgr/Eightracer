<?php

namespace Tests\Feature;

use App\Models\AlumniProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PrdFixtures;
use Tests\TestCase;

class TuitionRangesContractTest extends TestCase
{
    use RefreshDatabase, PrdFixtures;

    public function test_ranges_use_raw_latest_payments_not_averages_of_program_averages(): void
    {
        $first = $this->student(null, ['tahun_lulus' => 2026]);
        $admission = $this->admission($first);
        $campus = $admission->perguruan_tinggi_id;
        foreach ([[$first, $admission, 0], [$this->student(null, ['tahun_lulus' => 2026]), null, 1000000],
            [$this->student(null, ['tahun_lulus' => 2026]), null, 5000000],
            [$this->student(null, ['tahun_lulus' => 2026]), null, null]] as $index => [$student, $tracer, $fee]) {
            if (!$tracer) {
                $tracer = $this->admission($student);
                $tracer->update(['perguruan_tinggi_id' => $campus, 'program_studi' => $index === 2 ? 'Ekonomi' : 'Informatika']);
            }
            AlumniProgress::create(['siswa_id' => $student->id, 'study_tracer_id' => $tracer->id,
                'tahun_lulus' => 2026, 'tanggal_update' => today(), 'nominal_ukt' => $fee, 'is_current' => true]);
        }
        // A second offer must not duplicate the student or inherit this payment.
        $offer = $this->admission($first);
        $offer->update(['perguruan_tinggi_id' => $campus]);
        $this->signIn($this->account('administrator'));
        $r = $this->getJson('/api/analitik-beasiswa/sekolah')->assertOk();
        $r->assertJsonCount(1, 'data.financial.tuition_ranges.by_university')
            ->assertJsonPath('data.financial.tuition_ranges.by_university.0.count', 4)
            ->assertJsonPath('data.financial.tuition_ranges.by_university.0.stats.known', 3)
            ->assertJsonPath('data.financial.tuition_ranges.by_university.0.stats.unknown', 1)
            ->assertJsonPath('data.financial.tuition_ranges.by_university.0.stats.minimum', 0)
            ->assertJsonPath('data.financial.tuition_ranges.by_university.0.stats.maximum', 5000000)
            ->assertJsonPath('data.financial.tuition_ranges.by_university.0.stats.mean', 2000000)
            ->assertJsonPath('data.financial.tuition_ranges.by_university.0.golongan_minimum', null);
        $this->getJson('/api/dashboard/ukt-ipk')->assertOk()
            ->assertJsonPath('data.tuition_ranges.by_university.0.stats.mean', 2000000)
            ->assertJsonPath('data.ukt_alumni_diketahui', 3)
            ->assertJsonStructure(['data' => ['per_kampus_prodi' => [['universitas', 'nama_pt']]]]);
    }

    public function test_aid_flags_and_fixture_payments_do_not_fabricate_school_ukt(): void
    {
        $s = $this->student(null, ['tahun_lulus' => 2026]);
        $t = $this->admission($s);
        AlumniProgress::create(['siswa_id' => $s->id, 'study_tracer_id' => $t->id, 'tahun_lulus' => 2026,
            'tanggal_update' => today(), 'penerima_kipk' => true, 'nominal_ipi' => 0, 'nominal_ukt' => null]);
        $fake = $this->student(null, ['tahun_lulus' => 2026, 'catatan' => '{"fixture_key":"b4-local-alumni-20261111","synthetic":true}']);
        $fakeAdmission = $this->admission($fake);
        AlumniProgress::create(['siswa_id' => $fake->id, 'study_tracer_id' => $fakeAdmission->id,
            'tahun_lulus' => 2026, 'tanggal_update' => today(), 'nominal_ukt' => 12000000]);
        $this->signIn($this->account('administrator'));
        $this->getJson('/api/analitik-beasiswa/sekolah')->assertOk()
            ->assertJsonCount(1, 'data.financial.tuition_ranges.by_university')
            ->assertJsonPath('data.financial.ukt_alumni_diketahui', 0)
            ->assertJsonPath('data.financial.tuition_ranges.by_university.0.stats.minimum', null)
            ->assertJsonPath('data.financial.tuition_ranges.by_university.0.status', 'belum_tercatat');
        $this->getJson('/api/dashboard/ukt-ipk')->assertOk()
            ->assertJsonPath('data.ukt_alumni_diketahui', 0)
            ->assertJsonPath('data.tuition_ranges.by_program.0.stats.minimum', null);
    }
}

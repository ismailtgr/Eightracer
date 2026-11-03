<?php

namespace Tests\Feature;

use App\Models\SchoolScholarshipObservation;
use App\Services\School\ScholarshipSourceImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\PrdFixtures;
use Tests\TestCase;

class SchoolScholarshipSourceTest extends TestCase
{
    use RefreshDatabase, PrdFixtures;

    private function row(array $extra = []): array
    {
        return $extra + ['source_file_id' => '1i3igBq5PbH8TPk60Pz75M2Qfz8MrVYsA', 'source_sha256' => str_repeat('a', 64),
            'source_sheet' => 'MANDIRI', 'source_row' => 11, 'source_column' => 'I', 'tahun_lulus' => 2026,
            'siswa_id' => null, 'study_tracer_id' => null, 'nama_kolom' => 'BEASISWA', 'nilai_sumber' => 'BEASISWA TELADAN',
            'kampus_sumber' => 'Kampus Uji', 'prodi_sumber' => 'Prodi Uji'];
    }

    public function test_import_links_source_to_the_correct_admission_without_inventing_awards(): void
    {
        $s = $this->student(null, ['tahun_lulus' => 2026]);
        $t = $this->admission($s);
        $r = $this->row(['siswa_id' => $s->id, 'study_tracer_id' => $t->id]);
        $hashes = [$r['source_file_id'] => $r['source_sha256']];
        $service = app(ScholarshipSourceImporter::class);
        $this->assertSame(['created' => 1, 'existing' => 0], $service->import([$r], $hashes));
        $this->assertSame(['created' => 0, 'existing' => 1], $service->import([$r], $hashes));
        $this->assertDatabaseHas('school_scholarship_observations', ['siswa_id' => $s->id, 'study_tracer_id' => $t->id, 'jenis_keterangan' => 'referensi_beasiswa']);
        $this->assertDatabaseCount('alumni_progress', 0);
        $this->assertDatabaseCount('alumni_progress_beasiswa', 0);
        $this->assertDatabaseCount('basis_data_beasiswa', 0);
        $this->assertDatabaseHas('study_tracer', ['id' => $t->id, 'izinkan_tampil_di_buku' => false]);
    }

    public function test_kip_indicators_codes_and_fee_waivers_keep_distinct_meanings(): void
    {
        $this->assertSame(['jenis_keterangan' => 'indikator_kip', 'indikator_kip' => true], ScholarshipSourceImporter::classification('KIPK', 'YA'));
        $this->assertSame(['jenis_keterangan' => 'indikator_kip', 'indikator_kip' => false], ScholarshipSourceImporter::classification('KIP', 'Tidak'));
        foreach (['-', 'V', 'TPKS', 'KIP-K', 'BEBAS IPI', 'Retsumeikan Asia Pacific University'] as $v) {
            $this->assertNull(ScholarshipSourceImporter::classification('BEASISWA', $v)['indikator_kip']);
        }
        $this->assertSame('pembebasan_biaya', ScholarshipSourceImporter::classification('BEASISWA', 'BEBAS IPI')['jenis_keterangan']);
    }

    public function test_wrong_student_link_rolls_back_the_whole_batch(): void
    {
        $a = $this->student(null, ['tahun_lulus' => 2026]);
        $b = $this->student(null, ['tahun_lulus' => 2026]);
        $t = $this->admission($b);
        $first = $this->row();
        try {
            app(ScholarshipSourceImporter::class)->import([$first, $this->row(['source_row' => 12, 'siswa_id' => $a->id, 'study_tracer_id' => $t->id])], [$first['source_file_id'] => $first['source_sha256']]);
            $this->fail('A different student must never receive this source record.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('school_scholarship_observations', 0);
        }
    }

    public function test_changed_checksum_or_mapping_is_rejected(): void
    {
        $r = $this->row();
        $service = app(ScholarshipSourceImporter::class);
        try {
            $service->import([$r], [$r['source_file_id'] => str_repeat('b', 64)]);
            $this->fail('An unverified checksum must be rejected.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('school_scholarship_observations', 0);
        }
        $service->import([$r], [$r['source_file_id'] => $r['source_sha256']]);
        try {
            $service->import([$this->row(['nilai_sumber' => 'Changed'])], [$r['source_file_id'] => $r['source_sha256']]);
            $this->fail('Existing source mapping must not be overwritten.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('school_scholarship_observations', ['nilai_sumber' => 'BEASISWA TELADAN']);
        }
    }

    public function test_only_staff_can_read_private_school_source_records(): void
    {
        $r = $this->row();
        app(ScholarshipSourceImporter::class)->import([$r], [$r['source_file_id'] => $r['source_sha256']]);
        $this->getJson('/api/riwayat-beasiswa-sumber')->assertUnauthorized();
        $this->signIn($this->account());
        $this->getJson('/api/riwayat-beasiswa-sumber')->assertForbidden();
        $this->getJson('/api/riwayat-beasiswa-sumber/ringkasan')->assertForbidden();
        $this->signIn($this->account('administrator'));
        $this->getJson('/api/riwayat-beasiswa-sumber')->assertOk()->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.sumber.cell', 'I11')->assertJsonPath('data.0.status_beasiswa', 'belum_dikonfirmasi')
            ->assertJsonMissingPath('data.0.siswa.user');
        $this->getJson('/api/riwayat-beasiswa-sumber/ringkasan')->assertOk()->assertJsonPath('data.jumlah_catatan', 1);
        $this->getJson('/api/riwayat-beasiswa-sumber?jenis_keterangan=unknown')->assertUnprocessable();
    }
}

<?php

namespace Tests\Feature;

use App\Models\AlumniProgress;
use App\Models\BasisDataBeasiswa;
use App\Models\SchoolScholarshipObservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PrdFixtures;
use Tests\TestCase;

class ScholarshipRecipientsTest extends TestCase
{
    use RefreshDatabase, PrdFixtures;

    private function source($student, string $value, string $column = 'BEASISWA'): void
    {
        SchoolScholarshipObservation::create(['source_key' => hash('sha256', uniqid('', true)),
            'source_file_id' => 'test', 'source_sha256' => str_repeat('a',64), 'source_sheet' => 'test',
            'source_row' => 1,'source_column' => 'G', 'tahun_lulus' => 2026,'siswa_id' => $student->id,
            'nama_kolom' => $column,'nilai_sumber' => $value,'jenis_keterangan' => 'referensi_kip']);
    }

    public function test_recipient_directory_deduplicates_and_rejects_applications_and_examples(): void
    {
        $kip = $this->student(null,['tahun_lulus'=>2026,'nama_lengkap'=>'Budi KIP']);
        $award = $this->student(null,['tahun_lulus'=>2026,'nama_lengkap'=>'Ayu Penerima']);
        $pending = $this->student(null,['tahun_lulus'=>2026,'nama_lengkap'=>'Citra Pelamar']);
        $other = $this->student(null,['tahun_lulus'=>2026,'nama_lengkap'=>'Dina Lain']);
        $example = $this->student(null,['tahun_lulus'=>2026,'catatan'=>'{"fixture_key":"b4-local-alumni-20261111","synthetic":true}']);
        $this->source($kip,'V','KIPK');$this->source($kip,'KIP-K');$this->source($example,'KIP-K');
        $this->source($other,'Retsumeikan Asia Pacific University');
        $this->source($other,'Beasiswa belum dikonfirmasi');
        $program = BasisDataBeasiswa::create(['nama_beasiswa'=>'TELADAN','jenjang'=>'Kuliah','is_active'=>false]);
        foreach ([[$award,'diterima'],[$pending,'proses'],[$kip,'diterima']] as [$student,$status]) {
            $t=$this->admission($student);
            $p=AlumniProgress::create(['siswa_id'=>$student->id,'study_tracer_id'=>$t->id,'tahun_lulus'=>2026,'tanggal_update'=>today(),'is_current'=>true]);
            $p->alumniProgressBeasiswa()->create(['beasiswa_id'=>$program->id,'status_beasiswa'=>$status]);
        }
        $this->signIn($this->account('administrator'));
        $r=$this->getJson('/api/rekomendasi-beasiswa/penerima')->assertOk()->assertJsonPath('data.total',2);
        $this->assertEqualsCanonicalizing([$kip->id,$award->id],array_column($r->json('data.data'),'id'));
        $this->getJson('/api/rekomendasi-beasiswa/penerima?search=Ayu&angkatan=2026')->assertOk()->assertJsonPath('data.total',1)->assertJsonPath('data.data.0.id',$award->id);
        // Dedicated recipient scope cannot be disabled through the URL.
        $this->getJson('/api/rekomendasi-beasiswa/penerima?penerima_beasiswa=0')->assertOk()->assertJsonPath('data.total',2);
        $this->getJson('/api/siswa?penerima_beasiswa=1')->assertOk()->assertJsonPath('data.total',2);
        $this->getJson('/api/siswa')->assertOk()->assertJsonPath('data.total',5);
        $this->assertDatabaseCount('rekomendasi_beasiswa',0);
    }

    public function test_recipient_directory_requires_staff_authentication(): void
    {
        $this->getJson('/api/rekomendasi-beasiswa/penerima')->assertUnauthorized();
        $this->signIn($this->account());
        $this->getJson('/api/rekomendasi-beasiswa/penerima')->assertForbidden();
    }
}

<?php
namespace Tests\Feature;

use App\Models\AlumniProgress;
use App\Models\AlumniProgressBeasiswa;
use App\Models\BasisDataBeasiswa;
use App\Models\SchoolScholarshipObservation;
use App\Services\School\AlumniScholarshipEvidence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PrdFixtures;
use Tests\TestCase;

class AlumniScholarshipEvidenceTest extends TestCase
{
    use RefreshDatabase, PrdFixtures;

    private function source($student, $admission, string $value, string $column = 'BEASISWA'): void
    {
        SchoolScholarshipObservation::create(['source_key' => hash('sha256', uniqid()),
            'source_file_id' => 'school', 'source_sha256' => str_repeat('a',64),
            'source_sheet' => 'MANDIRI', 'source_row' => 1, 'source_column' => 'I',
            'tahun_lulus' => 2026, 'siswa_id' => $student->id, 'study_tracer_id' => $admission->id,
            'nama_kolom' => $column, 'nilai_sumber' => $value, 'jenis_keterangan' => 'referensi_beasiswa']);
    }

    public function test_each_acceptance_uses_its_own_sources_without_fabricated_semesters(): void
    {
        $s=$this->student(null,['tahun_lulus'=>2026]); $a=$this->admission($s); $b=$this->admission($s);
        $this->source($s,$a,'BEASISWA TELADAN'); $this->source($s,$b,'KIP K');
        $other=$this->student(); $this->source($other,$a,'BEASISWA ASING');
        $this->signIn($this->account('administrator'));
        $this->getJson('/api/b4/alumni/'.$a->id)->assertOk()
            ->assertJsonCount(0,'data.progress')->assertJsonCount(1,'data.scholarship_summary.beasiswa')
            ->assertJsonPath('data.scholarship_summary.beasiswa.0.nama_beasiswa','BEASISWA TELADAN')
            ->assertJsonPath('data.scholarship_summary.penerima_kipk',null)->assertDontSee('BEASISWA ASING');
        $this->getJson('/api/b4/alumni/'.$b->id)->assertOk()
            ->assertJsonPath('data.scholarship_summary.penerima_kipk',true)
            ->assertJsonPath('data.scholarship_summary.beasiswa.0.nama_beasiswa','KIP-K');
        $this->assertDatabaseCount('alumni_progress',0);
    }

    public function test_latest_progress_status_does_not_become_an_accepted_award_from_source(): void
    {
        $s=$this->student();$a=$this->admission($s);$this->source($s,$a,'KIP-K');
        $catalog=BasisDataBeasiswa::create(['nama_beasiswa'=>'KIP-K','jenjang'=>'Kuliah','durasi'=>8,'satuan_durasi'=>'semester']);
        $p=AlumniProgress::create(['siswa_id'=>$s->id,'study_tracer_id'=>$a->id,'tahun_lulus'=>2026,
            'periode'=>'Ganjil 2026/2027','semester'=>1,'is_current'=>true,'tanggal_update'=>today(),'penerima_kipk'=>false]);
        AlumniProgressBeasiswa::create(['alumni_progress_id'=>$p->id,'beasiswa_id'=>$catalog->id,'status_beasiswa'=>'ditolak']);
        $summary=app(AlumniScholarshipEvidence::class)->summarize($a);
        $this->assertFalse($summary['penerima_kipk']);
        $this->assertSame('ditolak',$summary['beasiswa'][0]['status']);
        $this->assertCount(1,$summary['beasiswa']);
    }

    public function test_unknown_and_campus_labels_never_create_awards_or_financial_amounts(): void
    {
        $s=$this->student();$a=$this->admission($s);
        $this->source($s,$a,'Retsumeikan Asia Pacific University');
        $summary=app(AlumniScholarshipEvidence::class)->summarize($a);
        $this->assertNull($summary['penerima_kipk']);$this->assertSame([],$summary['beasiswa']);
        $this->assertArrayNotHasKey('nominal_ukt',$summary);
        $this->source($s,$a,'BEBAS IPI');
        $this->assertSame('Bebas IPI',app(AlumniScholarshipEvidence::class)->summarize($a)['beasiswa'][0]['nama_beasiswa']);
    }

    public function test_published_projection_excludes_unpublished_progress_and_school_sources(): void
    {
        $s=$this->student();$a=$this->admission($s);$this->source($s,$a,'BEASISWA TELADAN');
        AlumniProgress::create(['siswa_id'=>$s->id,'study_tracer_id'=>$a->id,'tahun_lulus'=>2026,
            'periode'=>'Ganjil 2026/2027','semester'=>1,'is_current'=>true,'tanggal_update'=>today(),
            'is_published'=>false,'penerima_kipk'=>true]);
        $summary=app(AlumniScholarshipEvidence::class)->summarize($a,true,false);
        $this->assertNull($summary['penerima_kipk']);$this->assertSame([],$summary['beasiswa']);
        $a->forceFill(['izinkan_tampil_di_buku'=>true])->save();
        $this->app->instance('env','production');$this->signIn($this->account());
        $this->getJson('/api/jejak-alumni/'.$a->id)->assertOk()
            ->assertJsonPath('data.scholarship_summary.penerima_kipk',null)
            ->assertJsonCount(0,'data.scholarship_summary.beasiswa')->assertDontSee('BEASISWA TELADAN');
    }

    public function test_local_preview_preserves_actual_identity_and_source_without_demo_details(): void
    {
        $s=$this->student(null,['tahun_lulus'=>2026]);$a=$this->admission($s);
        $a->forceFill(['tahun_masuk_pt'=>null,'pilihan_ke'=>2])->save();$this->source($s,$a,'KIP-K');
        $this->app->instance('env','local');config(['b4.local_student_preview'=>true]);
        $this->signIn($this->account());$this->withServerVariables(['REMOTE_ADDR'=>'127.0.0.1']);
        $this->getJson('/api/jejak-alumni/'.$a->id)->assertOk()
            ->assertJsonPath('data.masuk_pt.pilihan_ke',2)->assertJsonPath('data.masuk_pt.tahun_masuk_pt',null)
            ->assertJsonPath('data.scholarship_summary.penerima_kipk',true)
            ->assertJsonCount(0,'data.progress')->assertDontSee('PRIVATE_PHONE');
    }
}

<?php

namespace Tests\Feature;

use App\Models\BasisDataBeasiswa;
use App\Models\PrestasiMurid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\PrdFixtures;
use Tests\TestCase;

/** Acceptance regression checks derived from the PRD use-case audit. */
class UseCaseAcceptanceAuditTest extends TestCase
{
    use PrdFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function catalogBody(string $name, string $stage): array
    {
        return ['nama_beasiswa'=>$name,'jenjang'=>$stage,'durasi'=>2,'satuan_durasi'=>'semester',
            'periode_apply'=>'Sesuai pengumuman penyelenggara',
            'kriteria'=>[['nama_kriteria'=>'TKA minimal','tipe_kriteria'=>'nilai_tka','operator'=>'>=','nilai_kriteria'=>'80']]];
    }

    private function publishAdmission(int $id, bool $publish=true): void
    {
        $this->patchJson('/api/study-tracer/'.$id.'/publikasi', [
            'is_published'=>$publish,'consent_reference'=>'Izin sintetis untuk uji',
            'persetujuan_dikonfirmasi'=>true,
        ])->assertOk();
    }

    public function test_uc02_uc06_uc24_student_registers_logs_in_reads_own_dashboard_and_logs_out(): void
    {
        $this->mailReady();
        $student=$this->student(null,['registration_email'=>'journey@example.test']);
        $body=['full_name'=>'Akun Journey','email'=>'journey@example.test','nisn'=>$student->nisn,
            'password'=>'Strong-Test123!','password_confirmation'=>'Strong-Test123!'];
        $challenge=$this->postJson('/api/register',$body)->assertAccepted()->json('challenge_id');
        $this->postJson('/api/register/verify',['challenge_id'=>$challenge,'otp'=>$this->code($body['email'],'register')])->assertCreated();
        $token=$this->postJson('/api/login',['email'=>$body['email'],'password'=>$body['password']])->assertOk()->json('token');
        $this->app['auth']->forgetGuards();
        $this->withToken($token);
        $this->getJson('/api/beranda')->assertOk()->assertJsonPath('data.nama_lengkap','Murid Uji')->assertJsonMissingPath('data.total_murid');
        $this->getJson('/api/siswa')->assertForbidden();
        $this->getJson('/api/profile')->assertOk()->assertJsonPath('data.full_name','Akun Journey');
        $this->postJson('/api/logout')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/me')->assertUnauthorized();
        // Logout revokes this device's token; other student sessions may remain.
        $this->assertDatabaseMissing('personal_access_tokens',['id'=>(int)explode('|',$token,2)[0]]);
    }

    public function test_uc13_student_reads_missing_data_status_but_cannot_run_admin_batch_calculation(): void
    {
        $owner=$this->account(); $student=$this->student($owner);
        $this->signIn($this->account('administrator'));
        $program=$this->postJson('/api/program-eligibilitas',['nama'=>'Program Audit',
            'kriteria'=>[['nama_kriteria'=>'TKA','tipe_kriteria'=>'nilai_tka','operator'=>'>=','nilai_kriteria'=>'80']]])->assertCreated()->json('data.id');
        $this->signIn($owner);
        $this->getJson('/api/eligibilitas/saya')->assertOk()->assertJsonPath('data.0.status','Belum dinilai');
        $this->postJson('/api/program-eligibilitas/'.$program.'/hitung',['siswa_ids'=>[$student->id]])->assertForbidden();
    }

    public function test_uc14_admission_has_conditional_scores_and_multiple_choices_do_not_overwrite(): void
    {
        $student=$this->student(); $template=$this->admission($student);
        $this->signIn($this->account('administrator'));
        $body=['siswa_id'=>$student->id,'perguruan_tinggi_id'=>$template->perguruan_tinggi_id,
            'strata_pendidikan'=>'S1','program_studi'=>'Fisika','status_penerimaan'=>'diterima','tahun_masuk_pt'=>2026];
        $this->postJson('/api/study-tracer',[...$body,'jalur_masuk'=>'SNBP'])->assertUnprocessable();
        $this->postJson('/api/study-tracer',[...$body,'jalur_masuk'=>'SNBT'])->assertUnprocessable();
        $first=$this->postJson('/api/study-tracer',[...$body,'jalur_masuk'=>'SNBP','nilai_rapor_pendaftaran'=>90,'pilihan_ke'=>1])->assertCreated()->json('data.id');
        $second=$this->postJson('/api/study-tracer',[...$body,'jalur_masuk'=>'SNBT','skor_utbk'=>700,'pilihan_ke'=>2])->assertCreated()->json('data.id');
        $this->assertNotSame($first,$second);
        $this->assertDatabaseCount('study_tracer',3);
    }

    public function test_uc16_published_admission_includes_achievement_reference_without_private_phone(): void
    {
        $alumni=$this->student(null,['tahun_lulus'=>2026]); $admission=$this->admission($alumni);
        $this->signIn($this->account('administrator'));
        $achievement=$this->postJson('/api/siswa/'.$alumni->id.'/pendukung/prestasi',[
            'jenis_prestasi'=>'Akademik','nama_kompetisi'=>'PrestasiAuditNasional',
            'tingkat'=>'Nasional','status_verifikasi'=>'terverifikasi',
        ])->assertCreated()->json('data.id');
        $admission->prestasi()->sync([$achievement]);
        $this->publishAdmission($admission->id);
        $reader=$this->account(); $this->student($reader); $this->signIn($reader);
        $response=$this->getJson('/api/jejak-alumni/'.$admission->id)->assertOk()->assertDontSee('PRIVATE_PHONE');
        $response->assertSee('PrestasiAuditNasional');
    }

    public function test_uc17_revoking_publication_closes_student_admission_and_timeline(): void
    {
        $alumni=$this->student(null,['tahun_lulus'=>2026,'tanggal_masuk_sekolah'=>'2023-07-01']);
        $admission=$this->admission($alumni); $admin=$this->account('administrator');
        $reader=$this->account(); $this->student($reader);
        $this->signIn($admin); $this->publishAdmission($admission->id);
        $this->signIn($reader);
        $this->getJson('/api/jejak-alumni/'.$admission->id)->assertOk()->assertJsonPath('data.timeline.0.type','masuk_sekolah');
        $this->signIn($admin); $this->publishAdmission($admission->id,false);
        $this->signIn($reader);
        $this->getJson('/api/jejak-alumni/'.$admission->id)->assertNotFound();
        $this->getJson('/api/jejak-alumni')->assertOk()->assertJsonCount(0,'data');
    }

    public function test_uc18_analytics_exposes_scholarship_status_changes_per_period(): void
    {
        $alumni=$this->student(null,['tahun_lulus'=>2026]); $admission=$this->admission($alumni);
        $scholarship=BasisDataBeasiswa::create(['nama_beasiswa'=>'RiwayatAudit','jenjang'=>'Kuliah','is_active'=>true]);
        $this->signIn($this->account('administrator'));
        foreach ([['Semester 1',1,'2026-08-01','diterima'],['Semester 2',2,'2026-10-01','ditolak']] as [$period,$semester,$date,$status]) {
            $this->postJson('/api/alumni/'.$alumni->id.'/progress',[
                'study_tracer_id'=>$admission->id,'periode'=>$period,'semester'=>$semester,
                'tahun_lulus'=>'2026','tanggal_update'=>$date,'status_pendidikan'=>'aktif',
                'beasiswa'=>[['beasiswa_id'=>$scholarship->id,'status_beasiswa'=>$status]],
            ])->assertCreated();
        }
        $trend=$this->getJson('/api/analitik-beasiswa')->assertOk()->json('data.tren_periode');
        $this->assertCount(2,$trend);
        $this->assertStringContainsString('diterima',json_encode($trend), 'Tren PRD harus menyediakan perubahan status beasiswa, bukan hanya jumlah KIP-K.');
        $this->assertStringContainsString('ditolak',json_encode($trend));
        $this->assertSame(1,$trend[0]['beasiswa'][0]['status']['diterima']);
        $this->assertSame(0,$trend[0]['beasiswa'][0]['status']['ditolak']);
        $this->assertSame(0,$trend[1]['beasiswa'][0]['status']['diterima']);
        $this->assertSame(1,$trend[1]['beasiswa'][0]['status']['ditolak']);
    }

    public function test_uc19_catalog_supports_both_stages_and_archiving_preserves_record(): void
    {
        $this->signIn($this->account('administrator'));
        $id=$this->postJson('/api/beasiswa',$this->catalogBody('LintasJenjangAudit','Keduanya'))->assertCreated()->assertJsonPath('data.jenjang','Keduanya')->json('data.id');
        $this->deleteJson('/api/beasiswa/'.$id)->assertOk();
        $this->assertDatabaseHas('basis_data_beasiswa',['id'=>$id,'is_active'=>false]);
        $this->assertDatabaseCount('basis_data_beasiswa',1);
    }

    public function test_uc19_catalog_search_returns_only_matching_scholarship(): void
    {
        $this->signIn($this->account('administrator'));
        $this->postJson('/api/beasiswa',$this->catalogBody('TargetAudit','SMA'))->assertCreated();
        $this->postJson('/api/beasiswa',$this->catalogBody('BeasiswaLain','Kuliah'))->assertCreated();
        $this->getJson('/api/beasiswa?search=TargetAudit')->assertOk()->assertJsonCount(1,'data')->assertJsonPath('data.0.nama_beasiswa','TargetAudit');
    }

    public function test_uc19_catalog_filter_excludes_other_stage(): void
    {
        $this->signIn($this->account('administrator'));
        $this->postJson('/api/beasiswa',$this->catalogBody('KhususSMAAudit','SMA'))->assertCreated();
        $this->postJson('/api/beasiswa',$this->catalogBody('KhususKuliahAudit','Kuliah'))->assertCreated();
        $this->getJson('/api/beasiswa?jenjang=SMA')->assertOk()->assertJsonCount(1,'data')->assertJsonPath('data.0.jenjang','SMA');
    }

    public function test_catalog_stage_filters_include_cross_stage_awards_and_reject_invalid_input(): void
    {
        $this->signIn($this->account('administrator'));
        foreach (['SMA', 'Kuliah', 'Keduanya'] as $stage) {
            $this->postJson('/api/beasiswa', $this->catalogBody('Beasiswa '.$stage, $stage))->assertCreated();
        }
        foreach (['SMA', 'Kuliah'] as $stage) {
            $rows = $this->getJson('/api/beasiswa?jenjang='.$stage)->assertOk()->assertJsonCount(2, 'data')->json('data');
            $this->assertEqualsCanonicalizing([$stage, 'Keduanya'], array_column($rows, 'jenjang'));
        }
        $this->getJson('/api/beasiswa?jenjang=Keduanya')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.jenjang', 'Keduanya');
        $this->getJson('/api/beasiswa?jenjang=TidakValid')->assertUnprocessable()->assertJsonValidationErrors('jenjang');
        $this->getJson('/api/beasiswa?search%5B0%5D=x')->assertUnprocessable()->assertJsonValidationErrors('search');
        $this->getJson('/api/beasiswa?search='.str_repeat('a', 101))->assertUnprocessable();
        $this->signIn($this->account());
        $this->getJson('/api/beasiswa?jenjang=SMA')->assertForbidden();
    }

    public function test_catalog_search_and_stage_are_retained_in_pagination_and_parameterized(): void
    {
        $this->signIn($this->account('administrator'));
        for ($i = 1; $i <= 21; $i++) {
            $this->postJson('/api/beasiswa', $this->catalogBody('NeedleAudit '.$i, 'SMA'))->assertCreated();
        }
        $this->postJson('/api/beasiswa', $this->catalogBody('NeedleAudit Kuliah', 'Kuliah'))->assertCreated();
        $this->postJson('/api/beasiswa', $this->catalogBody('Tidak cocok', 'SMA'))->assertCreated();
        $response = $this->getJson('/api/beasiswa?search=NeedleAudit&jenjang=SMA&page=2')
            ->assertOk()->assertJsonPath('total', 21)->assertJsonCount(1, 'data')->assertJsonPath('data.0.jenjang', 'SMA');
        parse_str(parse_url($response->json('first_page_url'), PHP_URL_QUERY), $query);
        $this->assertSame('NeedleAudit', $query['search']);
        $this->assertSame('SMA', $query['jenjang']);
        $this->getJson('/api/beasiswa?search='.rawurlencode("' OR 1=1 --"))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_student_achievement_projection_hides_private_evidence_and_other_student_links(): void
    {
        $alumni = $this->student(null, ['tahun_lulus' => 2026]);
        $admission = $this->admission($alumni);
        $own = PrestasiMurid::create([
            'siswa_id' => $alumni->id, 'jenis_prestasi' => 'Akademik',
            'nama_kompetisi' => 'Prestasi yang diizinkan', 'tingkat' => 'Nasional',
            'file_bukti_url' => 'private/PRIVATE_CERTIFICATE.pdf', 'bobot_persentase' => 90,
        ]);
        $other = PrestasiMurid::create([
            'siswa_id' => $this->student()->id, 'jenis_prestasi' => 'Akademik',
            'nama_kompetisi' => 'PRIVATE_OTHER_ACHIEVEMENT', 'tingkat' => 'Nasional',
        ]);
        // Simulate a bad legacy pivot; public serialization must still respect ownership.
        $admission->prestasi()->sync([$own->id, $other->id]);
        $this->signIn($this->account('administrator'));
        $this->publishAdmission($admission->id);
        $reader = $this->account();
        $this->student($reader);
        $this->signIn($reader);
        $this->getJson('/api/jejak-alumni/'.$admission->id)->assertOk()
            ->assertJsonCount(1, 'data.masuk_pt.prestasi')
            ->assertJsonPath('data.masuk_pt.prestasi.0.nama_kompetisi', 'Prestasi yang diizinkan')
            ->assertJsonMissingPath('data.masuk_pt.prestasi.0.siswa_id')
            ->assertJsonMissingPath('data.masuk_pt.prestasi.0.file_bukti_url')
            ->assertJsonMissingPath('data.masuk_pt.prestasi.0.bobot_persentase')
            ->assertJsonMissingPath('data.masuk_pt.prestasi.0.pivot')
            ->assertDontSee('PRIVATE_CERTIFICATE')->assertDontSee('PRIVATE_OTHER_ACHIEVEMENT')->assertDontSee('PRIVATE_PHONE');
        $this->getJson('/api/jejak-alumni')->assertOk()->assertJsonCount(1, 'data.0.prestasi')
            ->assertDontSee('PRIVATE_CERTIFICATE')->assertDontSee('PRIVATE_OTHER_ACHIEVEMENT');
    }

    public function test_scholarship_trend_uses_current_revision_unique_alumni_and_selected_scholarship(): void
    {
        $first = $this->student(null, ['tahun_lulus' => 2026]);
        $second = $this->student(null, ['tahun_lulus' => 2026]);
        $admission = $this->admission($first);
        $anotherAdmission = $this->admission($first);
        $secondAdmission = $this->admission($second);
        $award = BasisDataBeasiswa::create(['nama_beasiswa' => 'TrendAudit', 'jenjang' => 'Kuliah', 'is_active' => true]);
        $otherAward = BasisDataBeasiswa::create(['nama_beasiswa' => 'OtherAwardAudit', 'jenjang' => 'Kuliah', 'is_active' => true]);
        $body = fn ($tracer, $date, $status, $extra = []) => [
            'study_tracer_id' => $tracer->id, 'periode' => 'Semester 1 - 2026',
            'semester' => 1, 'tahun_lulus' => '2026', 'tanggal_update' => $date, 'status_pendidikan' => 'aktif',
            'beasiswa' => [['beasiswa_id' => $award->id, 'status_beasiswa' => $status], ...$extra],
        ];
        $this->signIn($this->account('administrator'));
        $id = $this->postJson('/api/alumni/'.$first->id.'/progress', $body($admission, '2026-08-01', 'proses'))
            ->assertCreated()->json('data.id');
        $this->patchJson('/api/alumni-progress/'.$id, $body($admission, '2026-09-01', 'ditolak', [
            ['beasiswa_id' => $otherAward->id, 'status_beasiswa' => 'diterima'],
        ]))->assertCreated();
        // Inserted later, but its earlier update date must not replace September's status.
        $this->postJson('/api/alumni/'.$first->id.'/progress', $body($anotherAdmission, '2026-08-15', 'diterima'))->assertCreated();
        $this->postJson('/api/alumni/'.$second->id.'/progress', $body($secondAdmission, '2026-09-01', 'diterima'))->assertCreated();
        $response = $this->getJson('/api/analitik-beasiswa')->assertOk()->assertJsonCount(2, 'data.tren_periode.0.beasiswa');
        $trend = collect($response->json('data.tren_periode.0.beasiswa'))->keyBy('beasiswa_id');
        $this->assertSame(2, $trend[$award->id]['jumlah_alumni']);
        $this->assertSame(['proses' => 0, 'diterima' => 1, 'ditolak' => 1], $trend[$award->id]['status']);
        $this->assertSame(1, $trend[$otherAward->id]['status']['diterima']);
        $this->getJson('/api/analitik-beasiswa?beasiswa_id='.$award->id)->assertOk()
            ->assertJsonCount(1, 'data.tren_periode.0.beasiswa')
            ->assertJsonPath('data.tren_periode.0.beasiswa.0.beasiswa_id', $award->id)
            ->assertJsonPath('data.tren_periode.0.beasiswa.0.status.ditolak', 1)
            ->assertJsonCount(1, 'data.distribusi_beasiswa')
            ->assertDontSee('OtherAwardAudit');
    }
}

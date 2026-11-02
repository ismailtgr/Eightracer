<?php

namespace Tests\Feature;

use App\Models\BasisDataBeasiswa;
use App\Models\PrivateAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PrdFixtures;
use Tests\TestCase;

class PrdAlumniPrivacyTest extends TestCase
{
    use PrdFixtures,RefreshDatabase;

    private function progressBody($s, $t): array
    {
        return ['study_tracer_id' => $t->id, 'periode' => 'Semester 1 - 2026', 'semester' => 1, 'tahun_lulus' => '2026', 'status_pendidikan' => 'aktif', 'tanggal_update' => today()->toDateString(), 'ipk_terkini' => 3.5, 'penerima_kipk' => true, 'nominal_ukt' => 1000000, 'nominal_ipi' => 5000000];
    }

    public function test_student_views_only_consented_admission_and_period_without_phone_or_private_money(): void
    {
        $s = $this->student(null, ['tahun_lulus' => 2026]);
        $t = $this->admission($s);
        $admin = $this->account('administrator');
        $reader = $this->account();
        $this->student($reader);
        $this->signIn($admin);
        $p = $this->postJson("/api/alumni/{$s->id}/progress", $this->progressBody($s, $t))->assertCreated()->json('data.id');
        $this->signIn($reader);
        $this->getJson("/api/jejak-alumni/{$t->id}")->assertNotFound();
        $this->signIn($admin);
        $consent = ['is_published' => true, 'consent_reference' => 'Bukti izin alumni', 'persetujuan_dikonfirmasi' => true];
        $this->patchJson("/api/study-tracer/{$t->id}/publikasi", $consent)->assertOk();
        $this->signIn($reader);
        $this->getJson("/api/jejak-alumni/{$t->id}")->assertOk()->assertJsonCount(0, 'data.progress')->assertDontSee('PRIVATE_PHONE')->assertJsonMissingPath('data.masuk_pt.siswa_id');
        $this->signIn($admin);
        $this->patchJson("/api/alumni-progress/{$p}/publikasi", [...$consent, 'publish_financials' => false])->assertOk();
        $this->signIn($reader);
        $this->getJson("/api/jejak-alumni/{$t->id}")->assertOk()->assertJsonCount(1, 'data.progress')->assertJsonMissingPath('data.progress.0.nominal_ukt')->assertJsonMissingPath('data.progress.0.nominal_ipi')->assertDontSee('PRIVATE_PHONE')->assertDontSee('consent_reference');
        $this->signIn($admin);
        $this->patchJson("/api/alumni-progress/{$p}/publikasi", [...$consent, 'publish_financials' => true])->assertOk();
        $this->signIn($reader);
        $this->getJson("/api/jejak-alumni/{$t->id}")->assertOk()->assertJsonPath('data.progress.0.nominal_ipi', '5000000.00');
    }

    public function test_progress_corrections_preserve_old_revision_and_reset_publication_and_period_duplicates(): void
    {
        $s = $this->student(null, ['tahun_lulus' => 2026]);
        $t = $this->admission($s);
        $this->signIn($this->account('administrator'));
        $body = $this->progressBody($s, $t);
        $id = $this->postJson("/api/alumni/{$s->id}/progress", $body)->assertCreated()->json('data.id');
        $this->postJson("/api/alumni/{$s->id}/progress", $body)->assertStatus(409);
        $new = $this->patchJson("/api/alumni-progress/{$id}", [...$body, 'ipk_terkini' => 3.8])->assertCreated()->assertJsonPath('data.revision', 2)->assertJsonPath('data.is_published', false)->json('data.id');
        $this->assertNotSame($id, $new);
        $this->assertDatabaseHas('alumni_progress', ['id' => $id, 'ipk_terkini' => 3.5, 'is_current' => false]);
        $this->patchJson("/api/alumni-progress/{$id}", $body)->assertStatus(409);
        $this->getJson('/api/analitik-beasiswa')->assertOk()->assertJsonPath('data.jumlah_alumni_tercatat', 1)->assertJsonPath('data.tren_periode.0.jumlah_alumni', 1);
    }

    public function test_ipi_is_money_only_on_mandiri_and_tracer_must_belong_to_student(): void
    {
        $s = $this->student(null, ['tahun_lulus' => 2026]);
        $t = $this->admission($s, 'SNBP');
        $other = $this->student(null, ['tahun_lulus' => 2026]);
        $this->signIn($this->account('administrator'));
        $body = $this->progressBody($s, $t);
        $this->postJson("/api/alumni/{$s->id}/progress", $body)->assertUnprocessable();
        unset($body['nominal_ipi']);
        $this->postJson("/api/alumni/{$other->id}/progress", $body)->assertUnprocessable();
        $this->postJson("/api/alumni/{$s->id}/progress", [...$body, 'ipk_terkini' => 4.5])->assertUnprocessable();
        $this->postJson("/api/alumni/{$s->id}/progress", $body)->assertCreated();
        $this->assertDatabaseCount('alumni_progress_beasiswa', 0);
    }

    public function test_alumni_can_be_added_without_fabricated_nisn_account_or_school_dates(): void
    {
        $template = $this->admission($this->student());
        $this->signIn($this->account('administrator'));
        $t = $this->postJson('/api/study-tracer', ['alumni_baru' => ['nama_lengkap' => 'Alumni Historis', 'tahun_lulus' => 2020], 'perguruan_tinggi_id' => $template->perguruan_tinggi_id, 'program_studi' => 'Fisika', 'strata_pendidikan' => 'S1', 'jalur_masuk' => 'Mandiri', 'status_penerimaan' => 'diterima', 'tahun_masuk_pt' => 2020])->assertCreated()->assertJsonPath('data.siswa.nisn', null)->assertJsonPath('data.siswa.user_id', null)->json('data.id');
        $events = $this->getJson('/api/alumni/'.$template->siswa_id.'/timeline')->assertOk()->json('data');
        $this->assertNotContains('masuk_sekolah', array_column($events, 'type'));
    }

    public function test_cohort_minimum_consent_publish_and_revocation_hide_members_and_private_fields(): void
    {
        $b = BasisDataBeasiswa::create(['nama_beasiswa' => 'Beasiswa Cohort', 'jenjang' => 'Kuliah', 'is_active' => true]);
        $admin = $this->account('administrator');
        $this->signIn($admin);
        $students = [];
        for ($i = 0; $i < 5; $i++) {
            $s = $this->student(null, ['tahun_lulus' => 2026]);
            $students[] = $s;
            $t = $this->admission($s);
            $this->postJson("/api/alumni/{$s->id}/progress", [...$this->progressBody($s, $t), 'beasiswa' => [['beasiswa_id' => $b->id, 'status_beasiswa' => 'diterima', 'nominal_beasiswa' => 10000000]], 'kategori_ekonomi' => '1_sampai_3_juta', 'referensi_ekonomi' => 'Form Uji'])->assertCreated();
            $this->patchJson("/api/siswa/{$s->id}/izin-analitik", ['izinkan' => true, 'referensi' => 'Bukti izin agregasi', 'persetujuan_dikonfirmasi' => true])->assertOk();
            if ($i === 3) {
                $this->postJson('/api/analitik-beasiswa/pola', ['beasiswa_id' => $b->id, 'angkatan' => 2026])->assertUnprocessable();
            }
        }
        $id = $this->postJson('/api/analitik-beasiswa/pola', ['beasiswa_id' => $b->id, 'angkatan' => 2026])->assertCreated()->assertJsonMissingPath('data.member_siswa_ids')->json('data.id');
        $reader = $this->account();
        $this->student($reader);
        $this->signIn($reader);
        $this->getJson('/api/pola-alumni')->assertOk()->assertJsonCount(0, 'data');
        $this->signIn($admin);
        $this->patchJson("/api/analitik-beasiswa/pola/{$id}/publikasi", ['is_published' => true])->assertOk();
        $this->signIn($reader);
        $this->getJson('/api/pola-alumni')->assertOk()->assertJsonPath('data.0.ringkasan.jumlah_penerima', 5)->assertJsonMissingPath('data.0.member_siswa_ids')->assertDontSee('PRIVATE_PHONE')->assertDontSee('10000000');
        $this->getJson('/api/analitik-beasiswa')->assertForbidden();
        $this->getJson('/api/analitik-beasiswa/export')->assertForbidden();
        $this->signIn($admin);
        $this->patchJson("/api/siswa/{$students[0]->id}/izin-analitik", ['izinkan' => false])->assertOk();
        $this->signIn($reader);
        $this->getJson('/api/pola-alumni')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_export_escapes_spreadsheet_formulas_and_attachment_cannot_be_read_by_another_student(): void
    {
        $u = $this->account();
        $s = $this->student($u, ['tahun_lulus' => 2026]);
        $t = $this->admission($s);
        $t->update(['program_studi' => '=SUM(1,1)']);
        $admin = $this->account('administrator');
        $this->signIn($admin);
        $this->postJson("/api/alumni/{$s->id}/progress", $this->progressBody($s, $t))->assertCreated();
        $csv = $this->get('/api/analitik-beasiswa/export')->assertOk()->streamedContent();
        $this->assertStringContainsString("'=SUM(1,1)", $csv);
        $this->assertStringNotContainsString('PRIVATE_PHONE', $csv);
        $file = PrivateAttachment::create(['siswa_id' => $s->id, 'path' => 'private-test.pdf', 'mime' => 'application/pdf', 'original_name' => 'test.pdf', 'size' => 10, 'uploaded_by' => $admin->id]);
        $this->signIn($this->account());
        $this->getJson('/api/lampiran/'.$file->id)->assertForbidden();
    }
}

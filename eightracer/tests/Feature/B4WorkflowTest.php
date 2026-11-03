<?php

namespace Tests\Feature;

use App\Jobs\CommitSchoolImport;
use App\Models\AlumniProgress;
use App\Models\BasisDataBeasiswa;
use App\Models\Hobi;
use App\Models\JenisBantuan;
use App\Models\MataPelajaran;
use App\Models\SchoolImportBatch;
use App\Models\Siswa;
use App\Models\StudentProfileSubmission;
use App\Services\Beasiswa\ScholarshipHistoryEvidence;
use App\Services\School\BulkImportEngine;
use App\Services\School\SchoolRecordWriter;
use App\Services\Security\ClamAvScanner;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\PrdFixtures;
use Tests\TestCase;

class B4WorkflowTest extends TestCase
{
    use PrdFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'gemini.api_key' => null, 'b4.school_email_domains' => ['school.test']]);
        Http::preventStrayRequests();
    }

    private function scanner(): void
    {
        $this->mock(ClamAvScanner::class)->shouldReceive('assertClean')->andReturnNull();
    }

    private function csv(string $text): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('source.csv', $text);
    }

    private function preview(string $module, string $csv, array $options = [])
    {
        return $this->post('/api/imports', [...['source' => 'synthetic-school', 'module' => $module, 'file' => $this->csv($csv)], ...$options], ['Accept' => 'application/json']);
    }

    private function finish(array $batch): SchoolImportBatch
    {
        Queue::fake();
        $this->postJson('/api/imports/'.$batch['id'].'/commit', ['commit_digest' => $batch['commit_digest']])->assertStatus(202);
        Queue::assertPushed(CommitSchoolImport::class);
        app(BulkImportEngine::class)->commit($batch['id']);

        return SchoolImportBatch::findOrFail($batch['id']);
    }

    private function catalog(): BasisDataBeasiswa
    {
        $b = BasisDataBeasiswa::create(['nama_beasiswa' => 'Beasiswa Uji', 'deskripsi' => 'Contoh sintetis untuk tes', 'jenjang' => 'SMA', 'is_active' => true]);
        $b->kriteriaKelayakanBeasiswa()->create(['nama_kriteria' => 'TKA', 'tipe_kriteria' => 'nilai_tka', 'operator' => '>=', 'nilai_kriteria' => '80']);

        return $b;
    }

    public function test_student_can_login_with_nisn_without_loosening_registration_or_admin_auth(): void
    {
        $u = $this->account();
        $s = $this->student($u);
        $this->postJson('/api/login', ['nisn' => $s->nisn, 'password' => 'Strong-Test123!'])->assertOk()->assertJsonPath('user.role', 'siswa');
        $this->postJson('/api/login', ['nisn' => $s->nisn, 'password' => 'incorrect'])->assertUnprocessable();
        $this->postJson('/api/login', ['nisn' => $s->nisn, 'email' => $u->email, 'password' => 'Strong-Test123!'])->assertUnprocessable();
    }

    public function test_only_explicitly_granted_verified_school_teacher_gets_bk_access_and_can_be_revoked(): void
    {
        $root = $this->account('administrator');
        $bk = $this->account('guru', ['email' => 'bk@school.test']);
        $other = $this->account('guru');
        $this->signIn($bk);
        $this->getJson('/api/dashboard/ukt-ipk')->assertForbidden();
        $this->signIn($root);
        $this->putJson("/api/school-staff/{$bk->id}/bk-access", ['enabled' => true])->assertOk();
        $this->signIn($bk);
        $this->getJson('/api/dashboard/ukt-ipk')->assertOk();
        $this->getJson('/api/siswa')->assertOk();
        $this->putJson("/api/school-staff/{$other->id}/bk-access", ['enabled' => true])->assertForbidden();
        $this->postJson('/api/login', ['email' => $bk->email, 'password' => 'Strong-Test123!'])->assertOk()->assertJsonPath('user.school_role', 'guru_bk');
        $this->assertEqualsWithDelta(72, now()->diffInHours($bk->tokens()->latest('id')->first()->expires_at), .1);
        $this->signIn($root);
        $this->putJson("/api/school-staff/{$bk->id}/bk-access", ['enabled' => false])->assertOk();
        $this->signIn($bk);
        $this->getJson('/api/dashboard/ukt-ipk')->assertForbidden();
    }

    public function test_bk_grant_cannot_be_given_to_wrong_domain_or_unverified_account(): void
    {
        $this->signIn($this->account('administrator'));
        foreach ([$this->account('guru', ['email' => 'teacher@other.test']), $this->account('guru', ['email' => 'unverified@school.test', 'email_verified_at' => null])] as $u) {
            $this->putJson("/api/school-staff/{$u->id}/bk-access", ['enabled' => true])->assertUnprocessable();
        }
        $this->assertDatabaseCount('school_staff_grants', 0);
    }

    public function test_student_initial_data_is_own_pending_and_approved_before_becoming_master(): void
    {
        $u = $this->account();
        $s = $this->student($u);
        $h = Hobi::create(['nama_hobi' => 'Bulu tangkis']);
        $this->signIn($u);
        $this->getJson('/api/profil-siswa/saya')->assertOk()->assertJsonPath('data.id', $s->id);
        $this->postJson('/api/profil-siswa/saya/submission', ['nisn' => '9999999999'])->assertUnprocessable();
        $id = $this->postJson('/api/profil-siswa/saya/submission', ['hobi' => [$h->id], 'alamat_rumah' => 'PRIVATE_NEW_ADDRESS'])->assertCreated()->json('data.id');
        $this->assertDatabaseCount('hobi_siswa', 0);
        $this->assertStringNotContainsString('PRIVATE_NEW_ADDRESS', DB::table('student_profile_submissions')->where('id', $id)->value('payload'));
        $this->signIn($this->account('administrator'));
        $this->patchJson("/api/profile-submissions/{$id}/review", ['status' => 'approved'])->assertOk();
        $this->assertDatabaseHas('hobi_siswa', ['siswa_id' => $s->id, 'hobi_id' => $h->id]);
        $this->signIn($this->account());
        $this->getJson("/api/profile-submissions/{$id}")->assertForbidden();
    }

    public function test_initial_submission_cannot_overwrite_a_master_edited_after_submission(): void
    {
        $u = $this->account();
        $s = $this->student($u);
        $this->signIn($u);
        $id = $this->postJson('/api/profil-siswa/saya/submission', ['alamat_rumah' => 'Proposed'])->assertCreated()->json('data.id');
        $s->update(['alamat_rumah' => 'Changed by school']);
        $this->signIn($this->account('administrator'));
        $this->patchJson("/api/profile-submissions/{$id}/review", ['status' => 'approved'])->assertStatus(409);
        $this->assertSame('pending', StudentProfileSubmission::find($id)->status);
    }

    private function progress($s, $t, string $period, int $semester, ?float $ukt, ?float $ipk, array $extra = []): AlumniProgress
    {
        return AlumniProgress::create([...['siswa_id' => $s->id, 'study_tracer_id' => $t->id, 'tahun_lulus' => '2026', 'periode' => $period, 'semester' => $semester, 'status_pendidikan' => 'aktif', 'tanggal_update' => today()->subDays(10 - $semester), 'nominal_ukt' => $ukt, 'ipk_terkini' => $ipk, 'is_current' => true], ...$extra]);
    }

    public function test_financial_filter_uses_latest_period_before_price_filter_and_keeps_unknowns_unknown(): void
    {
        $a = $this->student(null, ['tahun_lulus' => 2026]);
        $ta = $this->admission($a);
        $this->progress($a, $ta, 'S1', 1, 1000000, 3);
        $this->progress($a, $ta, 'S2', 2, 3000000, 3.7);
        $b = $this->student(null, ['tahun_lulus' => 2026]);
        $tb = $this->admission($b);
        $this->progress($b, $tb, 'S1', 1, 1500000, 3.5);
        $c = $this->student(null, ['tahun_lulus' => 2026]);
        $tc = $this->admission($c);
        $this->progress($c, $tc, 'S1', 1, null, null);
        $this->signIn($this->account('administrator'));
        $this->getJson('/api/dashboard/ukt-ipk?ukt_lt=2000000')->assertOk()->assertJsonPath('data.jumlah_alumni', 1)->assertJsonPath('data.ukt.minimum', 1500000);
        $this->getJson('/api/dashboard/ukt-ipk')->assertOk()->assertJsonPath('data.jumlah_alumni', 3)->assertJsonPath('data.ukt.jumlah_belum_tercatat', 1)->assertJsonPath('data.ukt.minimum', 1500000);
        $this->getJson('/api/dashboard/ukt-ipk?ukt_min=3000000&ukt_max=1000000')->assertUnprocessable();
        $this->signIn($this->account());
        $this->getJson('/api/dashboard/ukt-ipk')->assertForbidden();
    }

    public function test_gpa_trend_ignores_old_revisions_and_scholarship_filters_must_match_same_award(): void
    {
        $s = $this->student(null, ['tahun_lulus' => 2026]);
        $t = $this->admission($s);
        $b = $this->catalog();
        $other = $this->catalog();
        $old = $this->progress($s, $t, 'S1', 1, 1000000, 2, ['is_current' => false]);
        $p = $this->progress($s, $t, 'S1', 1, 1200000, 3.9, ['revision' => 2, 'supersedes_id' => $old->id]);
        $p->alumniProgressBeasiswa()->create(['beasiswa_id' => $b->id, 'status_beasiswa' => 'diterima']);
        $p->alumniProgressBeasiswa()->create(['beasiswa_id' => $other->id, 'status_beasiswa' => 'ditolak']);
        $this->signIn($this->account('administrator'));
        $this->getJson('/api/dashboard/ukt-ipk')->assertOk()->assertJsonPath('data.tren_ipk.0.ipk.rata_rata', 3.9)->assertJsonPath('data.tren_ipk.0.jumlah_alumni', 1);
        $this->getJson('/api/dashboard/ukt-ipk?beasiswa_id='.$b->id.'&status_beasiswa=ditolak')->assertOk()->assertJsonPath('data.jumlah_alumni', 0);
    }

    public function test_bulk_grades_cover_semester_one_to_five_and_commit_is_idempotent(): void
    {
        $this->scanner();
        $this->signIn($this->account('administrator'));
        $s = $this->student();
        $m = MataPelajaran::create(['nama_mapel' => 'Matematika', 'jenis_mapel' => 'A']);
        $csv = "nisn,semester,mata_pelajaran_id,nilai_akhir\n";
        for ($i = 1; $i <= 5; $i++) {
            $csv .= "{$s->nisn},{$i},{$m->id},90\n";
        }
        $batch = $this->preview('grades', $csv)->assertCreated()->assertJsonPath('data.status', 'validated')->json('data');
        $this->assertDatabaseCount('nilai_rapor', 0);
        $this->assertStringNotContainsString($s->nisn, DB::table('school_import_batches')->where('id', $batch['id'])->value('plan'));
        $row = $this->finish($batch);
        $this->assertSame('completed', $row->status);
        $this->assertDatabaseCount('nilai_rapor', 5);
        $this->postJson('/api/imports/'.$row->id.'/commit', ['commit_digest' => $batch['commit_digest']])->assertStatus(202);
        $this->preview('grades', $csv)->assertCreated()->assertJsonPath('data.id', $row->id);
        $this->assertDatabaseCount('nilai_rapor', 5);
    }

    public function test_bulk_invalid_or_duplicate_rows_never_partially_commit(): void
    {
        $this->scanner();
        $this->signIn($this->account('administrator'));
        $s = $this->student();
        $m = MataPelajaran::create(['nama_mapel' => 'Matematika', 'jenis_mapel' => 'A']);
        $batch = $this->preview('grades', "nisn,semester,mata_pelajaran_id,nilai_akhir\n{$s->nisn},1,{$m->id},90\n{$s->nisn},6,{$m->id},90\n")->assertCreated()->assertJsonPath('data.status', 'invalid')->json('data');
        $this->postJson('/api/imports/'.$batch['id'].'/commit', ['commit_digest' => $batch['commit_digest']])->assertStatus(409);
        $this->preview('grades', "nisn,semester,mata_pelajaran_id,nilai_akhir\n{$s->nisn},1,{$m->id},90\n{$s->nisn},1,{$m->id},80\n")->assertCreated()->assertJsonPath('data.status', 'invalid');
        $this->assertDatabaseCount('nilai_rapor', 0);
    }

    public function test_bulk_stale_sources_fail_and_other_staff_cannot_commit_owners_batch(): void
    {
        $this->scanner();
        $owner = $this->account('administrator');
        $this->signIn($owner);
        $s = $this->student();
        $m = MataPelajaran::create(['nama_mapel' => 'Matematika', 'jenis_mapel' => 'A']);
        $batch = $this->preview('grades', "nisn,semester,mata_pelajaran_id,nilai_akhir\n{$s->nisn},1,{$m->id},90\n")->assertCreated()->json('data');
        $this->signIn($this->account('administrator'));
        $this->getJson('/api/imports/'.$batch['id'])->assertNotFound();
        $this->postJson('/api/imports/'.$batch['id'].'/commit', ['commit_digest' => $batch['commit_digest']])->assertNotFound();
        $s->update(['nama_lengkap' => 'Master changed']);
        $this->signIn($owner);
        $this->assertSame('failed', $this->finish($batch)->status);
        $this->assertDatabaseCount('nilai_rapor', 0);
    }

    public function test_bulk_commit_rolls_back_all_rows_if_a_later_write_fails(): void
    {
        $this->scanner();
        $this->signIn($this->account('administrator'));
        $s = $this->student();
        $m = MataPelajaran::create(['nama_mapel' => 'Matematika', 'jenis_mapel' => 'A']);
        $batch = $this->preview('grades', "nisn,semester,mata_pelajaran_id,nilai_akhir\n{$s->nisn},1,{$m->id},90\n{$s->nisn},2,{$m->id},90\n")->assertCreated()->json('data');
        $this->app->instance(SchoolRecordWriter::class, new class extends SchoolRecordWriter
        {
            private int $calls = 0;

            public function saveGrades(Request $r, Siswa $siswa)
            {
                if (++$this->calls === 2) {
                    throw ValidationException::withMessages(['failure' => 'Injected failure']);
                }

return parent::saveGrades($r, $siswa);
            }
        });
        $this->assertSame('failed', $this->finish($batch)->status);
        $this->assertDatabaseCount('nilai_rapor', 0);
    }

    public function test_csv_active_content_and_unavailable_scanner_are_rejected(): void
    {
        $this->scanner();
        $this->signIn($this->account('administrator'));
        $this->preview('students', "nisn,nama_lengkap,jenis_kelamin\n0123456789,=HYPERLINK(1),L\n")->assertUnprocessable();
        $this->app->instance(ClamAvScanner::class, new class extends ClamAvScanner
        {
            public function assertClean(string $path): void
            {
                abort(503);
            }
        });
        $this->preview('students', "nisn,nama_lengkap,jenis_kelamin\n0123456789,Test Student,L\n")->assertStatus(503);
        $this->assertDatabaseCount('school_import_batches', 0);
    }

    public function test_financial_form_import_preserves_unknowns_and_keeps_old_revision(): void
    {
        $this->scanner();
        $this->signIn($this->account('administrator'));
        $s = $this->student(null, ['tahun_lulus' => 2026]);
        $t = $this->admission($s);
        $old = $this->progress($s, $t, '2026-S1', 1, 1000000, 3.7);
        $csv = "nisn,study_tracer_id,periode,semester,status_pendidikan,nominal_ukt,tanggal_update,external_id\n{$s->nisn},{$t->id},2026-S1,1,aktif,1500000,".today()->toDateString().",response-1\n";
        $batch = $this->preview('financial', $csv, ['mode' => 'upsert'])->assertCreated()->assertJsonPath('data.status', 'validated')->json('data');
        $this->assertSame('completed', $this->finish($batch)->status);
        $this->assertFalse($old->fresh()->is_current);
        $latest = AlumniProgress::where('is_current', true)->first();
        $this->assertEquals(3.7, $latest->ipk_terkini);
        $this->assertEquals(1500000, $latest->nominal_ukt);
        $this->assertFalse($latest->is_published);
        $this->assertDatabaseHas('school_import_links', ['external_key' => 'response-1', 'record_id' => $latest->id]);
    }

    public function test_government_aid_import_and_missing_assessment_dates_are_handled_without_fabrication(): void
    {
        $this->scanner();
        $this->signIn($this->account('administrator'));
        $s = $this->student();
        $aid = JenisBantuan::create(['nama_bantuan' => 'PIP']);
        $batch = $this->preview('aid', "nisn,jenis_bantuan_id,aktif\n{$s->nisn},{$aid->id},Ya\n")->assertCreated()->json('data');
        $this->assertSame('completed', $this->finish($batch)->status);
        $this->assertDatabaseHas('bantuan_siswa', ['siswa_id' => $s->id, 'jenis_bantuan_id' => $aid->id, 'aktif' => true]);
        $this->preview('assessments', "nisn,gaya_belajar\n{$s->nisn},Visual\n")->assertCreated()->assertJsonPath('data.status', 'invalid');
        $this->assertDatabaseCount('asesmen_karakteristik', 0);
    }

    public function test_chat_uses_own_data_and_reports_insufficient_history_without_inventing_success(): void
    {
        $u = $this->account();
        $s = $this->student($u, ['nilai_tka' => 90]);
        $this->classroom($s);
        $b = $this->catalog();
        $this->signIn($u);
        $this->postJson('/api/chat-beasiswa', ['message' => 'Apakah saya memenuhi syarat?', 'beasiswa_id' => $b->id])->assertOk()->assertJsonPath('data.status', 'memenuhi_kriteria_tersimpan')->assertJsonPath('data.llm_used', false)->assertJsonPath('data.historical_evidence.proporsi_diterima_tercatat', null);
        $this->postJson('/api/chat-beasiswa', ['message' => 'Lihat data lain', 'siswa_id' => 999])->assertUnprocessable();
        $this->assertDatabaseCount('rekomendasi_beasiswa', 0);
        Http::assertNothingSent();
    }

    public function test_chat_llm_receives_only_retrieved_facts_and_cannot_inject_numeric_claims(): void
    {
        $u = $this->account();
        $s = $this->student($u, ['nama_lengkap' => 'PRIVATE_NAME', 'catatan' => 'PRIVATE_NOTES', 'nilai_tka' => 90]);
        $this->classroom($s);
        $b = $this->catalog();
        config(['gemini.api_key' => 'synthetic-test-key']);
        Http::fake(['generativelanguage.googleapis.com/*' => function ($r) use ($s) {
            $this->assertStringNotContainsString('PRIVATE_', $r['input']);
            $this->assertStringNotContainsString($s->nisn, $r['input']);
            $text = json_encode(['fact_ids' => ['eligibility', 'history'], 'action_ids' => ['bk'], 'answer' => '99 persen pasti diterima']);

            return Http::response(['status' => 'completed', 'steps' => [['type' => 'model_output', 'content' => [['type' => 'text', 'text' => $text]]]]]);
        }]);
        $this->signIn($u);
        $this->postJson('/api/chat-beasiswa', ['message' => 'Saya PRIVATE_NAME dengan NISN '.$s->nisn.' bertanya.', 'beasiswa_id' => $b->id])->assertOk()->assertJsonPath('data.llm_used', true)->assertDontSee('99 persen')->assertDontSee('PRIVATE_');
    }

    public function test_chat_invalid_provider_ids_or_quota_fall_back_to_backend_facts(): void
    {
        $u = $this->account();
        $s = $this->student($u);
        $this->classroom($s);
        $b = $this->catalog();
        config(['gemini.api_key' => 'synthetic-test-key']);
        $this->signIn($u);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => 'PRIVATE_PROVIDER_SECRET'], 429)]);
        $this->postJson('/api/chat-beasiswa', ['message' => 'Cek syarat saya', 'beasiswa_id' => $b->id])->assertOk()->assertJsonPath('data.llm_used', false)->assertDontSee('PRIVATE_PROVIDER_SECRET');
    }

    public function test_history_rate_requires_both_outcomes_privacy_threshold_and_published_consent(): void
    {
        $b = $this->catalog();
        $make = function ($status, $consent = true) use ($b) {
            $s = $this->student(null, ['tahun_lulus' => 2026]);
            $s->forceFill(['izinkan_analitik' => $consent])->save();
            $t = $this->admission($s);
            $p = $this->progress($s, $t, '2026-S1', 1, null, null, ['is_published' => true]);
            $p->alumniProgressBeasiswa()->create(['beasiswa_id' => $b->id, 'status_beasiswa' => $status]);
        };
        for ($i = 0; $i < 5; $i++) {
            $make('diterima');
        }
        $make('ditolak');
        $make('ditolak', false);
        $service = app(ScholarshipHistoryEvidence::class);
        $this->assertNull($service->forScholarship($b->id)['proporsi_diterima_tercatat']);
        for ($i = 0; $i < 4; $i++) {
            $make('ditolak');
        }
        $result = $service->forScholarship($b->id);
        $this->assertSame(10, $result['jumlah_hasil_terminal']);
        $this->assertSame(.5, $result['proporsi_diterima_tercatat']);
    }

    public function test_pdf_download_runs_renderer_with_aggregate_only_and_fails_safely_if_unavailable(): void
    {
        $python = getenv('B4_TEST_PYTHON');
        if (! $python) {
            $this->markTestSkipped('Set B4_TEST_PYTHON and install tools/requirements-pdf.txt.');
        }
        config(['b4.python_binary' => $python]);
        $s = $this->student(null, ['nama_lengkap' => 'PRIVATE_STUDENT_NAME', 'tahun_lulus' => 2026]);
        $this->admission($s);
        $this->signIn($this->account('administrator'));
        $response = $this->get('/api/buku-alumni/pdf', ['Accept' => 'application/json'])->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringNotContainsString('PRIVATE_STUDENT_NAME', $response->getContent());
        config(['b4.python_binary' => 'missing-python-runtime-for-test']);
        $this->get('/api/buku-alumni/pdf', ['Accept' => 'application/json'])->assertStatus(503)->assertDontSee('missing-python-runtime');
        $this->signIn($this->account());
        $this->get('/api/buku-alumni/pdf', ['Accept' => 'application/json'])->assertForbidden();
    }

    private function workbook(array $sheets): UploadedFile
    {
        $entries = [];
        $book = '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        $rels = '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $index = 0;
        foreach ($sheets as $name => $rows) {
            $index++;
            $book .= '<sheet name="'.htmlspecialchars($name, ENT_XML1).'" sheetId="'.$index.'" r:id="rId'.$index.'"/>';
            $rels .= '<Relationship Id="rId'.$index.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$index.'.xml"/>';
            $xml = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
            foreach ($rows as $i => $row) {
                $n = $i + 1;
                $xml .= '<row r="'.$n.'">';
                foreach ($row as $j => $value) {
                    $xml .= '<c r="'.chr(65 + $j).$n.'" t="inlineStr"><is><t>'.htmlspecialchars((string) $value, ENT_XML1).'</t></is></c>';
                }
                $xml .= '</row>';
            }
            $entries['xl/worksheets/sheet'.$index.'.xml'] = $xml.'</sheetData></worksheet>';
        }
        $entries['xl/workbook.xml'] = $book.'</sheets></workbook>';
        $entries['xl/_rels/workbook.xml.rels'] = $rels.'</Relationships>';
        $entries['[Content_Types].xml'] = '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/></Types>';
        // A stored ZIP fixture assembled in memory avoids host-specific temporary ZIP rename behavior.
        $bytes = $central = '';
        $offset = 0;
        foreach ($entries as $name => $content) {
            $size = strlen($content);
            $length = strlen($name);
            $crc = crc32($content);
            $local = pack('VvvvvvVVVvv', 0x04034B50, 20, 0, 0, 0, 0, $crc, $size, $size, $length, 0).$name.$content;
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014B50, 20, 20, 0, 0, 0, 0, $crc, $size, $size, $length, 0, 0, 0, 0, 0, $offset).$name;
            $bytes .= $local;
            $offset += strlen($local);
        }
        $bytes .= $central.pack('VvvvvVVv', 0x06054B50, 0, 0, count($entries), count($entries), strlen($central), $offset, 0);

        return UploadedFile::fake()->createWithContent('school.xlsx', $bytes);
    }

    public function test_full_xlsx_multiple_tabs_header_mapping_and_excel_dates_work_without_losing_nisn_zero(): void
    {
        $this->scanner();
        $this->signIn($this->account('administrator'));
        $s = $this->student(null, ['nisn' => '0123456789']);
        $m = MataPelajaran::create(['nama_mapel' => 'Matematika', 'jenis_mapel' => 'A']);
        $serial = (int) Carbon::parse('1899-12-30')->diffInDays('2026-11-01');
        $tabs = ['Nilai' => [['Sumber sintetis'], [], ['NISN', 'Semester', 'Mapel', 'Nilai'], [$s->nisn, 1, $m->id, 88]],
            'Asesmen' => [['nisn', 'tanggal_asesmen', 'gaya_belajar', 'realistic_score', 'investigative_score', 'artistic_score', 'social_score', 'enterprising_score', 'conventional_score', 'mi_linguistik'], [$s->nisn, $serial, 'Visual', 30, 40, 50, 60, 70, 80, 75]],
            'Rekap' => [['Ringkasan sintetis']]];
        $sheets = [['sheet' => 'Nilai', 'module' => 'grades', 'header_row' => 3, 'columns' => ['nisn' => 'NISN', 'semester' => 'Semester', 'mata_pelajaran_id' => 'Mapel', 'nilai_akhir' => 'Nilai']], ['sheet' => 'Asesmen', 'module' => 'assessments']];
        $request = ['source' => 'synthetic-xlsx', 'file' => $this->workbook($tabs), 'sheets' => json_encode($sheets)];
        $this->post('/api/imports', $request, ['Accept' => 'application/json'])->assertUnprocessable();
        $request['file'] = $this->workbook($tabs);
        $request['ignore_sheets'] = json_encode(['Rekap']);
        $batch = $this->post('/api/imports', $request, ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.status', 'validated')->assertJsonPath('data.sample.1.data.tanggal_asesmen', '2026-11-01')->json('data');
        $this->assertSame('completed', $this->finish($batch)->status);
        $this->assertDatabaseCount('nilai_rapor', 1);
        $this->assertDatabaseCount('asesmen_karakteristik', 1);
        $this->assertDatabaseCount('asesmen_riasec', 1);
        $this->assertDatabaseCount('asesmen_multiple_intelligence', 1);
        $this->assertDatabaseHas('siswa', ['id' => $s->id, 'nisn' => '0123456789']);
    }

    public function test_students_and_economy_import_do_not_create_accounts_or_assume_missing_income_zero(): void
    {
        $this->scanner();
        $this->signIn($this->account('administrator'));
        $batch = $this->preview('students', "nisn,nama_lengkap,jenis_kelamin\n0123456789,Student Alpha,P\n")->assertCreated()->json('data');
        $this->assertSame('completed', $this->finish($batch)->status);
        $this->assertDatabaseHas('siswa', ['nisn' => '0123456789', 'user_id' => null]);
        $this->assertDatabaseCount('users', 1);
        $batch = $this->preview('economy', "nisn,ayah_nama,ayah_kondisi,ayah_penghasilan,ibu_nama,ibu_kondisi,ibu_penghasilan\n0123456789,Father,hidup,,Mother,hidup,0\n")->assertCreated()->json('data');
        $this->assertSame('completed', $this->finish($batch)->status);
        $this->assertDatabaseHas('orang_tua', ['jenis_orang_tua' => 'ayah', 'penghasilan' => null]);
        $this->assertDatabaseHas('orang_tua', ['jenis_orang_tua' => 'ibu', 'penghasilan' => 0]);
    }

    public function test_financial_form_wrong_alumni_mapping_is_invalid_and_locale_is_explicit(): void
    {
        $this->scanner();
        $this->signIn($this->account('administrator'));
        $s = $this->student(null, ['tahun_lulus' => 2026]);
        $other = $this->student(null, ['tahun_lulus' => 2026]);
        $t = $this->admission($s);
        $csv = "nisn;study_tracer_id;periode;semester;status_pendidikan;nominal_ukt;ipk_terkini;tanggal_update\n{$other->nisn};{$t->id};S1;1;aktif;Rp 1.500.000,00;3,75;".today()->toDateString()."\n";
        $this->preview('financial', $csv, ['delimiter' => 'semicolon', 'number_format' => 'id'])->assertCreated()->assertJsonPath('data.status', 'invalid');
        $csv = str_replace($other->nisn, $s->nisn, $csv);
        $batch = $this->preview('financial', $csv, ['delimiter' => 'semicolon', 'number_format' => 'id'])->assertCreated()->assertJsonPath('data.status', 'validated')->json('data');
        $this->assertSame('completed', $this->finish($batch)->status);
        $this->assertDatabaseHas('alumni_progress', ['siswa_id' => $s->id, 'nominal_ukt' => 1500000, 'ipk_terkini' => 3.75]);
    }

    public function test_expired_imports_cannot_commit_and_purge_removes_payload_only(): void
    {
        $this->scanner();
        $this->signIn($this->account('administrator'));
        $batch = $this->preview('students', "nisn,nama_lengkap,jenis_kelamin\n0123456789,Student Alpha,P\n")->assertCreated()->json('data');
        SchoolImportBatch::find($batch['id'])->update(['expires_at' => now()->subHour()]);
        $this->postJson('/api/imports/'.$batch['id'].'/commit', ['commit_digest' => $batch['commit_digest']])->assertStatus(409);
        $this->artisan('school:purge-import-plans')->assertSuccessful();
        $row = SchoolImportBatch::find($batch['id']);
        $this->assertSame('expired', $row->status);
        $this->assertNull($row->plan);
        $this->assertDatabaseCount('siswa', 0);
        $new = $this->preview('students', "nisn,nama_lengkap,jenis_kelamin\n0123456789,Student Alpha,P\n")->assertCreated()->assertJsonPath('data.status', 'validated')->json('data.id');
        $this->assertNotSame($row->id, $new);
    }

    public function test_google_form_response_id_cannot_be_remapped_to_a_different_alumnus(): void
    {
        $this->scanner();
        $this->signIn($this->account('administrator'));
        $a = $this->student(null, ['tahun_lulus' => 2026]);
        $ta = $this->admission($a);
        $b = $this->student(null, ['tahun_lulus' => 2026]);
        $tb = $this->admission($b);
        $header = "nisn,study_tracer_id,periode,semester,status_pendidikan,tanggal_update,external_id\n";
        $csv = $header."{$a->nisn},{$ta->id},S1,1,aktif,".today()->toDateString().",response-one\n";
        $first = $this->preview('financial', $csv)->assertCreated()->json('data');
        $this->assertSame('completed', $this->finish($first)->status);
        $second = $header."{$b->nisn},{$tb->id},S1,1,aktif,".today()->toDateString().",response-one\n";
        $this->preview('financial', $second)->assertCreated()->assertJsonPath('data.status', 'invalid');
        $this->assertDatabaseCount('alumni_progress', 1);
    }

    public function test_chat_resolves_unique_program_name_tokens_and_asks_when_ambiguous(): void
    {
        $u = $this->account();
        $s = $this->student($u);
        $this->classroom($s);
        $b = $this->catalog();
        $b->update(['nama_beasiswa' => 'Beasiswa Tanoto Contoh', 'penyelenggara' => 'Tanoto Foundation']);
        $this->signIn($u);
        $this->postJson('/api/chat-beasiswa', ['message' => 'Apakah saya eligible untuk Tanoto?'])->assertOk()->assertJsonPath('data.beasiswa_id', $b->id);
        $other = $this->catalog();
        $other->update(['nama_beasiswa' => 'Beasiswa Tanoto Varian', 'penyelenggara' => 'Tanoto Foundation']);
        $this->postJson('/api/chat-beasiswa', ['message' => 'Saya ingin Tanoto'])->assertOk()->assertJsonPath('data.status', 'selection_required');
        Http::assertNothingSent();
    }
}

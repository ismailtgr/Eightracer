<?php

namespace Tests\Feature;

use App\Models\MataPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PrdFixtures;
use Tests\TestCase;

class PrdSchoolModulesTest extends TestCase
{
    use PrdFixtures,RefreshDatabase;

    private function criterion(string $type = 'nilai_tka', string $value = '80'): array
    {
        return ['nama_kriteria' => 'Syarat Uji', 'tipe_kriteria' => $type, 'operator' => '>=', 'nilai_kriteria' => $value];
    }

    public function test_school_can_create_unclaimed_roster_and_reference_data_without_registering_users(): void
    {
        $admin = $this->account('administrator');
        $this->signIn($admin);
        $id = $this->postJson('/api/siswa', ['nama_lengkap' => 'Murid Master', 'nisn' => '0000000001', 'jenis_kelamin' => 'P', 'registration_email' => 'NEW@example.test'])->assertCreated()->assertJsonPath('data.user_id', null)->json('data.id');
        $this->assertDatabaseHas('siswa', ['id' => $id, 'registration_email' => 'new@example.test']);
        $this->assertDatabaseCount('users', 1);
        $h = $this->postJson('/api/referensi/hobi', ['nama_hobi' => 'Membaca'])->assertCreated()->json('data.id');
        $c = $this->postJson('/api/referensi/cita-cita', ['nama_cita_cita' => 'Peneliti'])->assertCreated()->json('data.id');
        $this->putJson("/api/siswa/{$id}/minat", ['hobi' => [$h], 'cita_cita' => [$c]])->assertOk();
        $this->postJson("/api/siswa/{$id}/pendukung/saudara", ['nama' => 'Saudara Uji', 'jenis_kelamin' => 'L', 'hubungan' => 'Adik', 'usia' => 10])->assertCreated();
        $this->getJson("/api/siswa/{$id}")->assertOk()->assertJsonCount(1, 'data.saudara_tiri');
        $this->signIn($this->account());
        $this->getJson('/api/referensi/hobi')->assertForbidden();
        $this->putJson("/api/siswa/{$id}/minat", ['hobi' => [], 'cita_cita' => []])->assertForbidden();
    }

    public function test_grades_are_per_semester_attendance_is_computed_and_duplicate_subjects_rejected(): void
    {
        $s = $this->student();
        $this->signIn($this->account('administrator'));
        $m = MataPelajaran::create(['nama_mapel' => 'Matematika', 'jenis_mapel' => 'A']);
        $row = ['mata_pelajaran_id' => $m->id, 'nilai_akhir' => 85];
        $this->putJson("/api/siswa/{$s->id}/nilai-rapor", ['semester' => 1, 'nilai' => [$row]])->assertOk();
        $this->putJson("/api/siswa/{$s->id}/nilai-rapor", ['semester' => 2, 'nilai' => [...[$row]]])->assertOk();
        $this->assertDatabaseCount('nilai_rapor', 2);
        $this->putJson("/api/siswa/{$s->id}/nilai-rapor", ['semester' => 1, 'nilai' => [$row, $row]])->assertUnprocessable();
        $this->putJson("/api/siswa/{$s->id}/nilai-rapor", ['semester' => 6, 'nilai' => [$row]])->assertUnprocessable();
        $body = ['semester' => 1, 'jumlah_hadir' => 90, 'jumlah_sakit' => 5, 'jumlah_izin' => 3, 'jumlah_alpa' => 2];
        $this->putJson("/api/siswa/{$s->id}/presensi", $body)->assertOk();
        $this->assertDatabaseHas('rekap_presensi', ['siswa_id' => $s->id, 'persentase_kehadiran' => 90]);
        $this->putJson("/api/siswa/{$s->id}/presensi", [...$body, 'persentase_kehadiran' => 100])->assertUnprocessable();
    }

    public function test_assessments_keep_correction_history_and_student_can_only_read_own(): void
    {
        $owner = $this->account();
        $s = $this->student($owner);
        $other = $this->account();
        $this->student($other);
        $this->signIn($this->account('administrator'));
        $data = ['tanggal_asesmen' => today()->toDateString(), 'gaya_belajar' => 'Visual', 'iq' => 115, 'riasec' => ['realistic_score' => 30, 'investigative_score' => 90, 'artistic_score' => 75, 'social_score' => 60, 'enterprising_score' => 20, 'conventional_score' => 10], 'multiple_intelligence' => [['jenis_kecerdasan' => 'Logis Matematis', 'skor' => 90]]];
        $id = $this->postJson("/api/siswa/{$s->id}/asesmen", $data)->assertCreated()->assertJsonPath('data.kode_riasec', 'IAS')->json('data.id');
        $this->postJson("/api/siswa/{$s->id}/asesmen", [...$data, 'iq' => 120, 'koreksi_dari_id' => $id])->assertCreated();
        $this->assertDatabaseHas('asesmen_karakteristik', ['id' => $id, 'is_current' => false, 'iq' => 115]);
        $this->assertDatabaseCount('asesmen_riasec', 2);
        $this->signIn($owner);
        $this->getJson('/api/asesmen/saya')->assertOk()->assertJsonCount(2, 'data.karakteristik');
        $this->postJson("/api/siswa/{$s->id}/asesmen", $data)->assertForbidden();
        $this->signIn($other);
        $this->getJson('/api/asesmen/saya?siswa_id='.$s->id)->assertOk()->assertJsonCount(0, 'data.karakteristik');
        $this->getJson("/api/siswa/{$s->id}/asesmen")->assertForbidden();
    }

    public function test_eligibility_missing_data_versions_and_dashboard_follow_current_data(): void
    {
        $owner = $this->account();
        $s = $this->student($owner);
        $admin = $this->account('administrator');
        $this->signIn($admin);
        $p = $this->postJson('/api/program-eligibilitas', ['nama' => 'Program Uji', 'kriteria' => [$this->criterion()]])->assertCreated()->json('data.id');
        $this->postJson("/api/program-eligibilitas/{$p}/hitung", ['siswa_ids' => [$s->id]])->assertOk()->assertJsonPath('data.0.status', 'Belum dinilai');
        $this->putJson("/api/siswa/{$s->id}", ['nilai_tka' => 90])->assertOk();
        $this->getJson('/api/beranda')->assertOk()->assertJsonPath('data.total_eligible', 1);
        $this->postJson("/api/program-eligibilitas/{$p}/hitung", ['siswa_ids' => [$s->id]])->assertOk()->assertJsonPath('data.0.status', 'Eligible');
        $this->putJson("/api/program-eligibilitas/{$p}", ['nama' => 'Program Uji', 'kriteria' => [$this->criterion('nilai_tka', '95')]])->assertOk()->assertJsonPath('data.version', 2);
        $this->getJson('/api/beranda')->assertOk()->assertJsonPath('data.total_eligible', 0);
        $this->assertDatabaseHas('hasil_program_eligibilitas', ['program_id' => $p, 'program_version' => 1, 'status' => 'Eligible']);
        $this->signIn($owner);
        $this->getJson('/api/eligibilitas/saya')->assertOk()->assertJsonPath('data.0.status', 'Tidak Eligible')->assertJsonMissingPath('data.0.alasan.0.syarat');
    }

    public function test_economy_requires_guardian_and_program_uses_its_achievement_weights_and_social_aid(): void
    {
        $s = $this->student();
        $this->signIn($this->account('administrator'));
        $aid = $this->postJson('/api/referensi/jenis-bantuan', ['nama_bantuan' => 'PIP'])->assertCreated()->json('data.id');
        $parents = [['jenis_orang_tua' => 'ayah', 'nama' => 'Ayah', 'kondisi' => 'meninggal', 'penghasilan' => 0], ['jenis_orang_tua' => 'ibu', 'nama' => 'Ibu', 'kondisi' => 'hidup', 'penghasilan' => 2000000]];
        $body = ['orang_tua' => $parents, 'bantuan_ids' => [$aid]];
        $this->putJson("/api/siswa/{$s->id}/ekonomi", $body)->assertUnprocessable();
        $this->assertDatabaseCount('orang_tua', 0);
        $this->putJson("/api/siswa/{$s->id}/ekonomi", [...$body, 'wali' => ['nama' => 'Wali', 'hubungan' => 'Ibu']])->assertOk();
        $program = ['nama' => 'Prestasi Sosial', 'bobot_prestasi' => ['Sekolah' => 10, 'Kabupaten' => 30, 'Kota' => 30, 'Provinsi' => 50, 'Nasional' => 80, 'Internasional' => 100], 'metode_prestasi' => 'tertinggi', 'bantuan_ids' => [$aid], 'mode_bantuan' => 'salah_satu', 'kriteria' => [$this->criterion('skor_prestasi', '80'), $this->criterion('bantuan_sosial_sesuai', '1')]];
        $p = $this->postJson('/api/program-eligibilitas', $program)->assertCreated()->json('data.id');
        $ach = ['jenis_prestasi' => 'Akademik', 'nama_kompetisi' => 'Olimpiade', 'tingkat' => 'Nasional', 'status_verifikasi' => 'pending'];
        $id = $this->postJson("/api/siswa/{$s->id}/pendukung/prestasi", $ach)->assertCreated()->json('data.id');
        $this->postJson("/api/program-eligibilitas/{$p}/hitung", ['siswa_ids' => [$s->id]])->assertOk()->assertJsonPath('data.0.status', 'Belum dinilai');
        $this->putJson("/api/siswa/{$s->id}/pendukung/prestasi/{$id}", [...$ach, 'status_verifikasi' => 'terverifikasi'])->assertOk();
        $this->postJson("/api/program-eligibilitas/{$p}/hitung", ['siswa_ids' => [$s->id]])->assertOk()->assertJsonPath('data.0.status', 'Eligible');
    }

    public function test_major_recommendations_need_configured_weights_admin_review_and_reject_changed_sources(): void
    {
        $owner = $this->account();
        $s = $this->student($owner);
        $admin = $this->account('administrator');
        $this->signIn($admin);
        $this->postJson("/api/siswa/{$s->id}/rekomendasi-jurusan/generate", ['semester' => 1])->assertUnprocessable();
        $m = MataPelajaran::create(['nama_mapel' => 'Matematika', 'jenis_mapel' => 'A']);
        $body = ['semester' => 1, 'nilai' => [['mata_pelajaran_id' => $m->id, 'nilai_akhir' => 90]]];
        $this->putJson("/api/siswa/{$s->id}/nilai-rapor", $body)->assertOk();
        $cluster = ['nama_klaster' => 'Teknik', 'bobot_komponen' => ['nilai' => 100, 'riasec' => 0, 'multiple_intelligence' => 0], 'mapel' => [['mata_pelajaran_id' => $m->id, 'bobot_persentase' => 100]]];
        $this->postJson('/api/klaster-jurusan', [...$cluster, 'bobot_komponen' => ['nilai' => 80, 'riasec' => 0, 'multiple_intelligence' => 0]])->assertUnprocessable();
        $this->postJson('/api/klaster-jurusan', $cluster)->assertCreated();
        $batch = $this->postJson("/api/siswa/{$s->id}/rekomendasi-jurusan/generate", ['semester' => 1])->assertCreated()->assertJsonPath('data.0.persentase_kesesuaian', '90.00')->json('data.0.batch_id');
        $this->signIn($owner);
        $this->getJson('/api/rekomendasi-jurusan/saya')->assertOk()->assertJsonCount(0, 'data');
        $this->signIn($admin);
        $this->putJson("/api/siswa/{$s->id}/nilai-rapor", ['semester' => 1, 'nilai' => [['mata_pelajaran_id' => $m->id, 'nilai_akhir' => 95]]])->assertOk();
        $this->patchJson("/api/rekomendasi-jurusan/{$batch}/review", ['status_review' => 'disetujui'])->assertStatus(409);
        $batch = $this->postJson("/api/siswa/{$s->id}/rekomendasi-jurusan/generate", ['semester' => 1])->assertCreated()->json('data.0.batch_id');
        $this->patchJson("/api/rekomendasi-jurusan/{$batch}/review", ['status_review' => 'disetujui', 'catatan_admin' => 'PRIVATE_NOTE'])->assertOk();
        $this->signIn($owner);
        $this->getJson('/api/rekomendasi-jurusan/saya')->assertOk()->assertJsonCount(1, 'data')->assertDontSee('PRIVATE_NOTE');
    }

    public function test_partial_or_backdated_assessment_does_not_discard_latest_riasec_and_mi(): void
    {
        $s = $this->student();
        $this->signIn($this->account('administrator'));
        $a = ['tanggal_asesmen' => today()->subDay()->toDateString(), 'riasec' => ['realistic_score' => 30, 'investigative_score' => 90, 'artistic_score' => 75, 'social_score' => 60, 'enterprising_score' => 20, 'conventional_score' => 10], 'multiple_intelligence' => [['jenis_kecerdasan' => 'Logis', 'skor' => 90]]];
        $id = $this->postJson("/api/siswa/{$s->id}/asesmen", $a)->assertCreated()->json('data.id');
        $latest = $this->postJson("/api/siswa/{$s->id}/asesmen", ['tanggal_asesmen' => today()->toDateString(), 'iq' => 115])->assertCreated()->json('data.id');
        $this->postJson("/api/siswa/{$s->id}/asesmen", ['tanggal_asesmen' => today()->subYear()->toDateString(), 'iq' => 100])->assertCreated()->assertJsonPath('data.is_current', false);
        $this->assertDatabaseHas('asesmen_karakteristik', ['id' => $latest, 'is_current' => true]);
        $this->postJson('/api/klaster-jurusan', ['nama_klaster' => 'Riset', 'kode_riasec_relevan' => 'IAS', 'kecerdasan_relevan' => 'Logis', 'bobot_komponen' => ['nilai' => 0, 'riasec' => 50, 'multiple_intelligence' => 50], 'mapel' => []])->assertCreated();
        $this->postJson("/api/siswa/{$s->id}/rekomendasi-jurusan/generate", ['semester' => 1])->assertCreated()->assertJsonPath('data.0.persentase_kesesuaian', '95.00');
    }

    public function test_criteria_reject_impossible_numeric_thresholds(): void
    {
        $this->signIn($this->account('administrator'));
        $this->postJson('/api/program-eligibilitas', ['nama' => 'Tidak valid', 'kriteria' => [$this->criterion('rata_rapor', '101')]])->assertUnprocessable();
        $this->postJson('/api/program-eligibilitas', ['nama' => 'Tidak valid', 'kriteria' => [$this->criterion('penghasilan_orang_tua', '-1')]])->assertUnprocessable();
    }

    public function test_admin_provisioning_has_no_default_password_and_cannot_add_second_admin(): void
    {
        $this->artisan('eightracer:admin', ['email' => 'admin@example.test', '--name' => 'Admin Uji'])->assertExitCode(1);
        $this->assertDatabaseCount('users', 0);
        $this->artisan('eightracer:admin', ['email' => 'admin@example.test', '--name' => 'Admin Uji', '--verify-email' => true])->expectsQuestion('Kata sandi baru (minimal 12 karakter, huruf besar/kecil, angka dan simbol)', 'Strong-Test123!')->expectsQuestion('Ulangi kata sandi', 'Strong-Test123!')->assertExitCode(0);
        $this->assertDatabaseHas('users', ['email' => 'admin@example.test', 'role' => 'administrator']);
        $this->artisan('eightracer:admin', ['email' => 'second@example.test', '--name' => 'Admin Dua', '--verify-email' => true])->assertExitCode(1);
        $this->assertDatabaseCount('users',1);
    }
}

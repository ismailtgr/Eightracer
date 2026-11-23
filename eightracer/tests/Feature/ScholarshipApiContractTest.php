<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\BasisDataBeasiswa;
use Illuminate\Support\Carbon;
use Tests\Concerns\PrdFixtures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScholarshipApiContractTest extends TestCase
{
    use RefreshDatabase, PrdFixtures;

    protected function setUp(): void
    {
        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE=:memory:');
        parent::setUp();
        config(['cache.default' => 'array']);
    }

    public function test_unauthenticated_api_is_json_even_without_accept_header(): void
    {
        $this->get('/api/rekomendasi-beasiswa')->assertUnauthorized()->assertJsonStructure(['message']);
    }

    public function test_frontend_preflight_allows_only_configured_origins(): void
    {
        config(['cors.allowed_origins' => ['http://localhost:5173']]);
        $this->options('/api/beasiswa', [], [
            'Origin' => 'http://localhost:5173',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'authorization,content-type',
        ])->assertSuccessful()->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
        $response = $this->options('/api/beasiswa', [], [
            'Origin' => 'https://not-configured.example',
            'Access-Control-Request-Method' => 'POST',
        ]);
        $this->assertNotSame('https://not-configured.example', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_bearer_token_login_me_and_logout_contract(): void
    {
        $user = User::forceCreate(['username' => 'contract-admin', 'email' => 'contract-admin@example.test', 'email_verified_at' => now(), 'password' => 'contract-password', 'role' => 'administrator']);
        $token = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'contract-password'])
            ->assertOk()->assertJsonPath('user.role', 'administrator')->json('token');
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertOk()->assertJsonPath('id', $user->id);
        $this->withToken($token)->postJson('/api/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_catalog_edit_lifecycle_preserves_archive_and_replaces_criteria(): void
    {
        $admin = User::forceCreate(['username' => 'edit-admin', 'email' => 'edit-admin@example.test', 'email_verified_at' => now(), 'password' => 'contract-password', 'role' => 'administrator']);
        $this->signIn($admin);
        $payload = ['nama_beasiswa' => 'UJI Kontrak', 'jenjang' => 'SMA', 'is_active' => true,
            'kriteria' => [['nama_kriteria' => 'Rapor', 'tipe_kriteria' => 'rata_rapor', 'operator' => '>=', 'nilai_kriteria' => '85', 'bobot' => 2]]];
        $id = $this->postJson('/api/beasiswa', $payload)->assertCreated()->json('data.id');
        $this->getJson('/api/beasiswa/'.$id)->assertOk()->assertJsonPath('data.nama_beasiswa', 'UJI Kontrak')
            ->assertJsonPath('data.kriteria_kelayakan_beasiswa.0.tipe_kriteria', 'rata_rapor');
        $this->deleteJson('/api/beasiswa/'.$id)->assertOk();
        $this->getJson('/api/beasiswa/'.$id)->assertOk()->assertJsonPath('data.is_active', false);
        $payload['kriteria'][0]['nilai_kriteria'] = '90';
        $this->putJson('/api/beasiswa/'.$id, $payload)->assertOk()->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.kriteria_kelayakan_beasiswa.0.nilai_kriteria', '90');
        $this->assertDatabaseCount('basis_data_beasiswa', 1);
        $this->assertDatabaseCount('kriteria_kelayakan_beasiswa', 1);
        $this->getJson('/api/beasiswa/999999')->assertNotFound();
    }

    public function test_student_cannot_read_or_mutate_staff_catalog(): void
    {
        $scholarship = BasisDataBeasiswa::create(['nama_beasiswa' => 'UJI Privat', 'jenjang' => 'SMA', 'is_active' => true]);
        $student = User::forceCreate(['username' => 'edit-student', 'email' => 'edit-student@example.test', 'email_verified_at' => now(), 'password' => 'contract-password', 'role' => 'siswa']);
        $this->signIn($student);
        $this->getJson('/api/beasiswa/'.$scholarship->id)->assertForbidden();
        $this->putJson('/api/beasiswa/'.$scholarship->id, [])->assertForbidden();
        $this->deleteJson('/api/beasiswa/'.$scholarship->id)->assertForbidden();
        $this->postJson('/api/beasiswa/bulk', ['rows' => [['nama_beasiswa' => 'UJI Bulk Siswa', 'jenjang' => 'SMA',
            'kriteria' => [['nama_kriteria' => 'Rapor', 'tipe_kriteria' => 'rata_rapor', 'operator' => '>=', 'nilai_kriteria' => '85']]]]])->assertForbidden();
        $this->assertDatabaseHas('basis_data_beasiswa', ['id' => $scholarship->id, 'is_active' => true]);
    }

    public function test_program_and_registration_status_are_separate_read_only_fields(): void
    {
        Carbon::setTestNow('2026-11-04 03:00:00 UTC');
        try {
            $this->signIn($this->account('administrator'));
            $payload = ['nama_beasiswa' => 'UJI September Oktober', 'jenjang' => 'SMA', 'periode_apply' => 'September–Oktober',
                'tanggal_buka' => '2026-09-01', 'tanggal_tutup' => '2026-10-31',
                'kriteria' => [['nama_kriteria' => 'Rapor', 'tipe_kriteria' => 'rata_rapor', 'operator' => '>=', 'nilai_kriteria' => '85']]];
            $id = $this->postJson('/api/beasiswa', $payload)->assertCreated()->assertJsonPath('data.is_active', true)
                ->assertJsonPath('data.status_program', 'aktif')->assertJsonPath('data.status_pendaftaran', 'ditutup')->json('data.id');
            $this->getJson('/api/beasiswa')->assertOk()->assertJsonPath('data.0.status_pendaftaran', 'ditutup');
            $this->putJson('/api/beasiswa/'.$id, $payload + ['status_pendaftaran' => 'dibuka'])->assertUnprocessable();
            $this->deleteJson('/api/beasiswa/'.$id)->assertOk();
            $this->getJson('/api/beasiswa/'.$id)->assertOk()->assertJsonPath('data.status_program', 'diarsipkan')
                ->assertJsonPath('data.status_pendaftaran', 'tidak_tersedia');
            $this->putJson('/api/beasiswa/'.$id, $payload + ['is_active' => true])->assertOk()
                ->assertJsonPath('data.status_program', 'aktif')->assertJsonPath('data.status_pendaftaran', 'ditutup');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_date_validation_checks_existing_dates_when_optional_update_fields_are_omitted(): void
    {
        $this->signIn($this->account('administrator'));
        $payload = ['nama_beasiswa' => 'UJI Jadwal', 'jenjang' => 'SMA', 'tanggal_buka' => '2026-09-01', 'tanggal_tutup' => '2026-10-31',
            'kriteria' => [['nama_kriteria' => 'Rapor', 'tipe_kriteria' => 'rata_rapor', 'operator' => '>=', 'nilai_kriteria' => '85']]];
        $id = $this->postJson('/api/beasiswa', $payload)->assertCreated()->json('data.id');
        $closeOnly = $payload;
        unset($closeOnly['tanggal_buka']);
        $closeOnly['tanggal_tutup'] = '2026-08-31';
        $this->putJson('/api/beasiswa/'.$id, $closeOnly)->assertUnprocessable()->assertJsonValidationErrors('tanggal_tutup');
        $openOnly = $payload;
        unset($openOnly['tanggal_tutup']);
        $openOnly['tanggal_buka'] = '2026-11-01';
        $this->putJson('/api/beasiswa/'.$id, $openOnly)->assertUnprocessable()->assertJsonValidationErrors('tanggal_tutup');
        $this->assertDatabaseHas('basis_data_beasiswa', ['id' => $id, 'tanggal_buka' => '2026-09-01', 'tanggal_tutup' => '2026-10-31']);
    }

    public function test_csv_template_payload_is_accepted_and_persisted_with_its_criteria(): void
    {
        $this->signIn($this->account('administrator'));
        $payload = ['nama_beasiswa' => 'Beasiswa Prestasi Akademik', 'jenjang' => 'SMA', 'durasi' => 12, 'satuan_durasi' => 'bulan',
            'periode_apply' => '2026-07-01 s.d. 2026-08-31', 'penyelenggara' => 'Yayasan Pendidikan Maju',
            'deskripsi' => 'Beasiswa untuk siswa berprestasi tinggi.', 'tanggal_buka' => '2026-07-01', 'tanggal_tutup' => '2026-08-31',
            'link_pendaftaran' => 'https://yayasan.example.com/daftar', 'is_active' => true,
            'kriteria' => [
                ['nama_kriteria' => 'Rata-rata rapor minimum', 'tipe_kriteria' => 'rata_rapor', 'operator' => '>=', 'nilai_kriteria' => '85', 'bobot' => 1.5],
                ['nama_kriteria' => 'Kehadiran minimum', 'tipe_kriteria' => 'persentase_kehadiran', 'operator' => '>=', 'nilai_kriteria' => '90'],
            ]];
        $id = $this->postJson('/api/beasiswa', $payload)->assertCreated()->json('data.id');
        $this->assertDatabaseHas('basis_data_beasiswa', ['id' => $id, 'nama_beasiswa' => 'Beasiswa Prestasi Akademik', 'jenjang' => 'SMA',
            'durasi' => 12, 'satuan_durasi' => 'bulan', 'periode_apply' => '2026-07-01 s.d. 2026-08-31',
            'penyelenggara' => 'Yayasan Pendidikan Maju', 'tanggal_buka' => '2026-07-01', 'tanggal_tutup' => '2026-08-31',
            'link_pendaftaran' => 'https://yayasan.example.com/daftar', 'is_active' => true]);
        $this->assertDatabaseCount('kriteria_kelayakan_beasiswa', 2);
        $this->assertDatabaseHas('kriteria_kelayakan_beasiswa', ['beasiswa_id' => $id, 'tipe_kriteria' => 'rata_rapor', 'nilai_kriteria' => '85', 'bobot' => 1.5]);
        $this->assertDatabaseHas('kriteria_kelayakan_beasiswa', ['beasiswa_id' => $id, 'tipe_kriteria' => 'persentase_kehadiran', 'nilai_kriteria' => '90', 'bobot' => null]);
    }

    public function test_catalog_rejects_duplicate_name_and_level_but_allows_other_level_and_self_update(): void
    {
        $this->signIn($this->account('administrator'));
        $payload = ['nama_beasiswa' => 'UJI Duplikat', 'jenjang' => 'SMA',
            'kriteria' => [['nama_kriteria' => 'Rapor', 'tipe_kriteria' => 'rata_rapor', 'operator' => '>=', 'nilai_kriteria' => '85']]];
        $id = $this->postJson('/api/beasiswa', $payload)->assertCreated()->json('data.id');
        $this->postJson('/api/beasiswa', [...$payload, 'nama_beasiswa' => '  uji duplikat  '])->assertUnprocessable()
            ->assertJsonValidationErrors('nama_beasiswa');
        $this->postJson('/api/beasiswa', [...$payload, 'jenjang' => 'Kuliah'])->assertCreated();
        $this->putJson('/api/beasiswa/'.$id, [...$payload, 'deskripsi' => 'Perubahan data yang sama'])->assertOk();
        $this->assertDatabaseCount('basis_data_beasiswa', 2);
        $this->assertDatabaseHas('basis_data_beasiswa', ['id' => $id, 'deskripsi' => 'Perubahan data yang sama']);
    }

    public function test_bulk_import_saves_every_row_and_its_criteria_in_one_request(): void
    {
        $this->signIn($this->account('administrator'));
        $first = ['nama_beasiswa' => 'Beasiswa Prestasi Akademik', 'jenjang' => 'SMA', 'durasi' => 12, 'satuan_durasi' => 'bulan',
            'periode_apply' => 'Juli - Agustus', 'penyelenggara' => 'Yayasan Pendidikan Maju', 'is_active' => true,
            'kriteria' => [
                ['nama_kriteria' => 'Rata-rata rapor minimum', 'tipe_kriteria' => 'rata_rapor', 'operator' => '>=', 'nilai_kriteria' => '85', 'bobot' => 1.5],
                ['nama_kriteria' => 'Kehadiran minimum', 'tipe_kriteria' => 'persentase_kehadiran', 'operator' => '>=', 'nilai_kriteria' => '90'],
            ]];
        $second = ['nama_beasiswa' => 'Beasiswa Kuliah Unggulan', 'jenjang' => 'Kuliah', 'is_active' => true,
            'kriteria' => [['nama_kriteria' => 'IPK minimum', 'tipe_kriteria' => 'ipk', 'operator' => '>=', 'nilai_kriteria' => '3.2']]];

        $this->postJson('/api/beasiswa/bulk', ['rows' => [$first, $second]])
            ->assertCreated()->assertJsonCount(2, 'data')->assertJsonPath('message', '2 beasiswa berhasil ditambahkan ke katalog.');

        $this->assertDatabaseCount('basis_data_beasiswa', 2);
        $this->assertDatabaseHas('basis_data_beasiswa', ['nama_beasiswa' => 'Beasiswa Prestasi Akademik', 'jenjang' => 'SMA', 'durasi' => 12, 'satuan_durasi' => 'bulan']);
        $this->assertDatabaseHas('basis_data_beasiswa', ['nama_beasiswa' => 'Beasiswa Kuliah Unggulan', 'jenjang' => 'Kuliah']);
        $this->assertDatabaseCount('kriteria_kelayakan_beasiswa', 3);
    }

    public function test_bulk_import_rolls_back_every_row_when_one_row_is_rejected(): void
    {
        $this->signIn($this->account('administrator'));
        $existing = ['nama_beasiswa' => 'UJI Katalog Lama', 'jenjang' => 'SMA',
            'kriteria' => [['nama_kriteria' => 'Rapor', 'tipe_kriteria' => 'rata_rapor', 'operator' => '>=', 'nilai_kriteria' => '85']]];
        $this->postJson('/api/beasiswa', $existing)->assertCreated();

        $new = ['nama_beasiswa' => 'UJI Katalog Baru', 'jenjang' => 'SMA',
            'kriteria' => [['nama_kriteria' => 'Rapor', 'tipe_kriteria' => 'rata_rapor', 'operator' => '>=', 'nilai_kriteria' => '80']]];
        $this->postJson('/api/beasiswa/bulk', ['rows' => [$new, [...$existing, 'nama_beasiswa' => '  uji katalog lama  ']]])
            ->assertUnprocessable()->assertJsonValidationErrors('rows.1.nama_beasiswa');

        $this->assertDatabaseCount('basis_data_beasiswa', 1);
        $this->assertDatabaseCount('kriteria_kelayakan_beasiswa', 1);
        $this->assertDatabaseMissing('basis_data_beasiswa', ['nama_beasiswa' => 'UJI Katalog Baru']);
    }

    public function test_bulk_import_rejects_duplicate_rows_inside_the_same_file_and_oversized_batches(): void
    {
        $this->signIn($this->account('administrator'));
        $row = ['nama_beasiswa' => 'UJI Batch', 'jenjang' => 'SMA',
            'kriteria' => [['nama_kriteria' => 'Rapor', 'tipe_kriteria' => 'rata_rapor', 'operator' => '>=', 'nilai_kriteria' => '85']]];

        $this->postJson('/api/beasiswa/bulk', ['rows' => [$row, [...$row, 'nama_beasiswa' => '  uji batch  ']]])
            ->assertUnprocessable()->assertJsonValidationErrors('rows.1.nama_beasiswa');
        $this->assertDatabaseCount('basis_data_beasiswa', 0);

        $this->postJson('/api/beasiswa/bulk', ['rows' => array_fill(0, 21, $row)])
            ->assertUnprocessable()->assertJsonValidationErrors('rows');
        $this->postJson('/api/beasiswa/bulk', ['rows' => []])
            ->assertUnprocessable()->assertJsonValidationErrors('rows');
        $this->assertDatabaseCount('basis_data_beasiswa', 0);
    }

    public function test_database_index_blocks_duplicate_name_and_level_written_outside_the_api(): void
    {
        BasisDataBeasiswa::create(['nama_beasiswa' => 'UJI Indeks', 'jenjang' => 'SMA', 'is_active' => true]);

        try {
            \Illuminate\Support\Facades\DB::table('basis_data_beasiswa')->insert([
                'nama_beasiswa' => '  uji indeks  ', 'jenjang' => 'SMA', 'is_active' => true,
            ]);
            $this->fail('Unique index nama+jenjang tidak menolak duplikat.');
        } catch (\Illuminate\Database\QueryException $exception) {
            $this->assertContains((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505']);
        }
    }
}

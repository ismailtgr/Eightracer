<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class B4IntegrationTestSeeder extends Seeder
{
    public function run(): void
    {
        // This seed must never run against the school's database.
        if (config('database.connections.'.config('database.default').'.database') !== 'eightracer_b4_uji_20261105'
            || DB::table('siswa')->exists() || DB::table('users')->exists()) {
            throw new \RuntimeException('B4 fixtures require an empty, dedicated integration-test database.');
        }
        $passwords = [getenv('B4_TEST_ADMIN_PASSWORD'), getenv('B4_TEST_STUDENT_PASSWORD')];
        if (min(array_map('strlen', $passwords)) < 20) {
            throw new \RuntimeException('Supply private test passwords through the environment.');
        }
        DB::transaction(function () use ($passwords) {
            foreach ([['id' => 900001, 'email' => 'admin.b4.uji@eightracer.test', 'role' => 'administrator', 'full_name' => 'UJI B4 Admin'],
                ['id' => 900002, 'email' => 'siswa.b4.uji@eightracer.test', 'role' => 'siswa', 'full_name' => 'UJI B4 Siswa']] as $i => $row) {
                User::forceCreate([...$row, 'username' => $row['email'], 'password' => $passwords[$i], 'email_verified_at' => now()]);
            }
            foreach ([900001 => 'Siswa', 900002 => 'Alumni', 900003 => 'Pembanding'] as $id => $kind) {
                DB::table('siswa')->insert(['id' => $id, 'user_id' => $id === 900001 ? 900002 : null,
                    'nisn' => '999990000'.($id - 900000), 'nama_lengkap' => 'UJI B4 '.$kind.' Sintetis', 'jenis_kelamin' => 'P',
                    'nilai_tka' => 88, 'tahun_lulus' => $id === 900002 ? 2025 : null,
                    'catatan' => 'DATA SINTETIS untuk integrasi FE, bukan data sekolah.', 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('kelas')->insert(['id' => 900001, 'tingkat' => '12', 'nama_kelas' => 'UJI XII', 'tahun_ajaran' => '2026/2027']);
            DB::table('riwayat_kelas')->insert(['siswa_id' => 900001, 'kelas_id' => 900001, 'tanggal_mulai' => '2026-07-01']);
            DB::table('mata_pelajaran')->insert(['id' => 900001, 'nama_mapel' => 'UJI B4 Matematika', 'jenis_mapel' => 'A']);
            foreach ([900001, 900002] as $student) {
                foreach (range(1, 5) as $semester) {
                    DB::table('nilai_rapor')->insert(['siswa_id' => $student, 'mata_pelajaran_id' => 900001, 'semester' => $semester, 'nilai_akhir' => 85 + $semester]);
                }
                foreach (['ayah', 'ibu'] as $parent) {
                    DB::table('orang_tua')->insert(['siswa_id' => $student, 'jenis_orang_tua' => $parent, 'nama' => 'UJI Orang Tua Sintetis', 'kondisi' => 'hidup', 'penghasilan' => 1000000]);
                }
            }
            DB::table('perguruan_tinggi')->insert(['id' => 900001, 'nama_pt' => 'UJI Universitas Sintetis', 'jenis_pt' => 'PTN', 'kode_pt' => 'UJI-B4']);
            DB::table('study_tracer')->insert(['id' => 900001, 'siswa_id' => 900002, 'perguruan_tinggi_id' => 900001,
                'strata_pendidikan' => 'S1', 'program_studi' => 'UJI Informatika', 'jalur_masuk' => 'SNBP', 'status_penerimaan' => 'diterima',
                'nilai_rapor_pendaftaran' => 88, 'tahun_masuk_pt' => 2025, 'izinkan_tampil_di_buku' => false]);
            DB::table('basis_data_beasiswa')->insert(['id' => 900001, 'nama_beasiswa' => 'UJI B4 Beasiswa Sintetis',
                'penyelenggara' => 'UJI Penyelenggara', 'deskripsi' => 'Contoh untuk pengujian; bukan penawaran beasiswa nyata.', 'jenjang' => 'Keduanya', 'is_active' => true]);
            DB::table('kriteria_kelayakan_beasiswa')->insert([
                ['beasiswa_id' => 900001, 'nama_kriteria' => 'Rapor minimum', 'tipe_kriteria' => 'rata_rapor', 'operator' => '>=', 'nilai_kriteria' => '85', 'bobot' => 2],
                ['beasiswa_id' => 900001, 'nama_kriteria' => 'Penghasilan orang tua', 'tipe_kriteria' => 'penghasilan_orang_tua', 'operator' => '<=', 'nilai_kriteria' => '3000000', 'bobot' => 1],
            ]);
            DB::table('alumni_progress')->insert(['id' => 900001, 'siswa_id' => 900002, 'study_tracer_id' => 900001,
                'tahun_lulus' => '2025', 'status_pendidikan' => 'aktif', 'ipk_terkini' => 3.65, 'tanggal_update' => '2026-11-01',
                'periode' => 'UJI 2026 Ganjil', 'semester' => 3, 'nominal_ukt' => 1500000, 'penerima_kipk' => false]);
            DB::table('program_eligibilitas')->insert(['id' => 900001, 'nama' => 'UJI B4 Program Rapor', 'kriteria' => json_encode([
                ['nama_kriteria' => 'Rapor', 'tipe_kriteria' => 'rata_rapor', 'operator' => '>=', 'nilai_kriteria' => '85', 'bobot' => 1],
            ]), 'version' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        });
    }
}

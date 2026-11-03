<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Administrator;
use App\Models\Siswa;
use App\Models\MataPelajaran;
use App\Models\NilaiRapor;
use App\Models\AsesmenKarakteristik;
use App\Models\KlasterProdi;
use App\Models\RekomendasiProdi;
use App\Models\PeriodeEligibilitas;
use App\Models\HasilEligibilitas;
use App\Models\DetailSkorEligibilitas;

class B2DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 0. Akun Pengguna & Profil Administrator
        $adminUser = User::updateOrCreate(
            ['username' => 'admin'],
            [
                'password' => Hash::make('admin123'),
                'role' => 'administrator',
            ]
        );

        Administrator::updateOrCreate(
            ['user_id' => $adminUser->id],
            [
                'nip' => '198001012005011001',
                'nama_lengkap' => 'Administrator Sistem',
            ]
        );

        // 1. Akun Pengguna (Role Siswa)
        $user = User::updateOrCreate(
            ['username' => 'siswa_b2_test'],
            [
                'password' => Hash::make('password123'),
                'role' => 'siswa',
            ]
        );

        // 2. Profil Siswa
        $siswa = Siswa::updateOrCreate(
            ['user_id' => $user->id],
            [
                'nisn' => '0061234567',
                'nis' => '20241001',
                'nama_lengkap' => 'Ahmad B2 Pratama',
                'nama_panggilan' => 'Ahmad',
                'jenis_kelamin' => 'L',
                'tempat_lahir' => 'Jakarta',
                'tanggal_lahir' => '2006-05-15',
                'agama' => 'Islam',
                'golongan_darah' => 'O',
                'alamat_rumah' => 'Jl. Pendidikan No. 8, Jakarta',
                'tinggal_bersama' => 'Orang Tua',
                'anak_ke' => 1,
                'warna_favorit' => 'Biru',
                'makanan_favorit' => 'Nasi Goreng',
                'bahasa_keseharian' => 'Indonesia',
                'permainan_online' => 'Mobile Legends',
                'permainan_offline' => 'Futsal',
                'nilai_tka' => 88.50,
                'catatan' => 'Siswa berprestasi akademik fokus SNBP & Saintek',
            ]
        );

        // 3. Master Data Pendukung: Mata Pelajaran
        $mapelMatematika = MataPelajaran::firstOrCreate(
            ['nama_mapel' => 'Matematika Umum'],
            [
                'jenis_mapel' => 'U',
                'kelompok' => 'Umum',
                'tersedia_sebagai_pilihan' => false,
                'deskripsi' => 'Mata pelajaran wajib matematika',
            ]
        );

        $mapelInformatika = MataPelajaran::firstOrCreate(
            ['nama_mapel' => 'Informatika'],
            [
                'jenis_mapel' => 'P',
                'kelompok' => 'Pilihan',
                'tersedia_sebagai_pilihan' => true,
                'deskripsi' => 'Mata pelajaran peminatan ilmu komputer',
            ]
        );

        $mapelBahasaInggris = MataPelajaran::firstOrCreate(
            ['nama_mapel' => 'Bahasa Inggris'],
            [
                'jenis_mapel' => 'U',
                'kelompok' => 'Umum',
                'tersedia_sebagai_pilihan' => false,
                'deskripsi' => 'Mata pelajaran wajib bahasa inggris',
            ]
        );

        // 4. Data Nilai Rapor (Semester 1 s/d 5)
        $nilaiSampel = [
            1 => [
                $mapelMatematika->id => 85.00,
                $mapelInformatika->id => 88.00,
                $mapelBahasaInggris->id => 84.00,
            ],
            2 => [
                $mapelMatematika->id => 87.00,
                $mapelInformatika->id => 90.00,
                $mapelBahasaInggris->id => 85.00,
            ],
            3 => [
                $mapelMatematika->id => 89.00,
                $mapelInformatika->id => 92.00,
                $mapelBahasaInggris->id => 88.00,
            ],
            4 => [
                $mapelMatematika->id => 90.00,
                $mapelInformatika->id => 94.00,
                $mapelBahasaInggris->id => 89.00,
            ],
            5 => [
                $mapelMatematika->id => 92.00,
                $mapelInformatika->id => 95.00,
                $mapelBahasaInggris->id => 91.00,
            ],
        ];

        foreach ($nilaiSampel as $semester => $mapels) {
            foreach ($mapels as $mapelId => $nilai) {
                NilaiRapor::updateOrCreate(
                    [
                        'siswa_id' => $siswa->id,
                        'mata_pelajaran_id' => $mapelId,
                        'semester' => $semester,
                    ],
                    [
                        'nilai_akhir' => $nilai,
                    ]
                );
            }
        }

        // 5. Data Asesmen Karakteristik (Hanya RIASEC dan Multiple Intelligence)
        AsesmenKarakteristik::updateOrCreate(
            ['siswa_id' => $siswa->id],
            [
                'tipe_kepribadian' => null,
                'kode_riasec' => 'IRC',
                'kecerdasan_majemuk_dominan' => 'Logis-Matematis',
                'tanggal_asesmen' => now(),
            ]
        );

        // 6. Master Data Pendukung: Klaster Prodi
        $klasterInformatika = KlasterProdi::firstOrCreate(
            ['nama_klaster' => 'Ilmu Komputer & Informatika'],
            [
                'kode_riasec_relevan' => 'IRC',
                'kecerdasan_relevan' => 'Logis-Matematis',
                'deskripsi' => 'Klaster program studi Teknik Informatika, Sistem Informasi, dan Ilmu Komputer.',
            ]
        );

        // 7. Rekomendasi Program Studi
        RekomendasiProdi::updateOrCreate(
            [
                'siswa_id' => $siswa->id,
                'klaster_id' => $klasterInformatika->id,
                'semester' => 5,
            ],
            [
                'study_tracer_id' => null,
                'nilai_rata_siswa' => 90.13,
                'persentase_kesesuaian' => 92.50,
                'alasan_rekomendasi' => 'Nilai Matematika dan Informatika konsisten meningkat, didukung kecerdasan dominan Logis-Matematis dan kode RIASEC IRC.',
                'tanggal_rekomendasi' => now(),
            ]
        );

        // 8. Periode Eligibilitas & Hasil Eligibilitas
        $periode = PeriodeEligibilitas::firstOrCreate(
            ['tahun_ajaran' => '2025/2026'],
            [
                'kuota_siswa' => 50,
                'is_active' => true,
            ]
        );

        $hasilEligibilitas = HasilEligibilitas::updateOrCreate(
            [
                'siswa_id' => $siswa->id,
                'periode_id' => $periode->id,
            ],
            [
                'total_skor_akhir' => 91.2500,
                'peringkat' => 1,
                'status_eligible' => 'Eligible',
                'tanggal_kalkulasi' => now(),
            ]
        );

        // 9. Detail Skor Eligibilitas
        DetailSkorEligibilitas::updateOrCreate(
            ['hasil_id' => $hasilEligibilitas->id],
            [
                'skor_raw_rapor' => 90.1333,
                'skor_norm_rapor' => 90.1333,
                'skor_raw_tka' => 88.5000,
                'skor_norm_tka' => 88.5000,
                'skor_raw_prestasi' => 95.0000,
                'skor_norm_prestasi' => 95.0000,
                'skor_raw_presensi' => 98.0000,
                'skor_norm_presensi' => 98.0000,
                'skor_raw_karakter' => 92.0000,
                'skor_norm_karakter' => 92.0000,
                'skor_raw_organisasi' => 85.0000,
                'skor_norm_organisasi' => 85.0000,
            ]
        );
    }
}

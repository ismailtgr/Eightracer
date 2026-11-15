<?php

namespace Tests\Feature;

use App\Models\AlumniProgress;
use App\Models\BantuanSiswa;
use App\Models\BasisDataBeasiswa;
use App\Models\CatatanKarakter;
use App\Models\CitaCita;
use App\Models\Hobi;
use App\Models\JenisBantuan;
use App\Models\Kelas;
use App\Models\KlasterProdi;
use App\Models\MataPelajaran;
use App\Models\MediaSosial;
use App\Models\OrangTua;
use App\Models\PerguruanTinggi;
use App\Models\PrestasiMurid;
use App\Models\ProgramEligibilitas;
use App\Models\RekapPresensi;
use App\Models\RekomendasiBeasiswa;
use App\Models\RekomendasiBeasiswaDetail;
use App\Models\RiwayatOrganisasi;
use App\Models\SaudaraTiri;
use App\Models\Siswa;
use App\Models\StudentProfileSubmission;
use App\Models\StudyTracer;
use App\Services\Beasiswa\GeminiScholarshipExplainer;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\PrdFixtures;
use Tests\TestCase;

class BackendCrudDatabaseVerificationTest extends TestCase
{
    use RefreshDatabase, PrdFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Http::preventStrayRequests();
    }

    /**
     * 1. Verifikasi CRUD Siswa: Create, Read, Update, Delete & Database Persistence
     */
    public function test_siswa_crud_lifecycle_and_database_persistence(): void
    {
        $admin = $this->account('administrator');
        $this->signIn($admin);

        // CREATE
        $payload = [
            'nisn' => '9876543210',
            'nis' => '12345/26',
            'nama_lengkap' => 'Budi Santoso',
            'nama_panggilan' => 'Budi',
            'jenis_kelamin' => 'L',
            'tempat_lahir' => 'Malang',
            'tanggal_lahir' => '2008-05-15',
            'agama' => 'Islam',
            'alamat_rumah' => 'Jl. Veteran No. 8 Malang',
            'registration_email' => 'budi.santoso@example.test',
            'nilai_tka' => 88.5,
        ];

        $createResponse = $this->postJson('/api/siswa', $payload);
        $createResponse->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.nama_lengkap', 'Budi Santoso')
            ->assertJsonPath('data.nisn', '9876543210');

        $siswaId = $createResponse->json('data.id');

        // Pastikan tersimpan di database
        $this->assertDatabaseHas('siswa', [
            'id' => $siswaId,
            'nisn' => '9876543210',
            'nama_lengkap' => 'Budi Santoso',
            'nama_panggilan' => 'Budi',
            'jenis_kelamin' => 'L',
            'tempat_lahir' => 'Malang',
            'alamat_rumah' => 'Jl. Veteran No. 8 Malang',
            'registration_email' => 'budi.santoso@example.test',
            'nilai_tka' => 88.5,
        ]);

        // READ (Index & Show)
        $this->getJson('/api/siswa?search=Budi')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $siswaId);

        $this->getJson('/api/siswa/' . $siswaId)
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.id', $siswaId)
            ->assertJsonPath('data.nama_lengkap', 'Budi Santoso');

        // UPDATE
        $updatePayload = [
            'nama_lengkap' => 'Budi Santoso Nugroho',
            'nama_panggilan' => 'Budi Nugroho',
            'alamat_rumah' => 'Jl. Ijen No. 10 Malang',
            'nilai_tka' => 92.0,
        ];

        $updateResponse = $this->putJson('/api/siswa/' . $siswaId, $updatePayload);
        $updateResponse->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.nama_lengkap', 'Budi Santoso Nugroho')
            ->assertJsonPath('data.alamat_rumah', 'Jl. Ijen No. 10 Malang');

        // Pastikan perubahan masuk ke database
        $this->assertDatabaseHas('siswa', [
            'id' => $siswaId,
            'nama_lengkap' => 'Budi Santoso Nugroho',
            'nama_panggilan' => 'Budi Nugroho',
            'alamat_rumah' => 'Jl. Ijen No. 10 Malang',
            'nilai_tka' => 92.0,
        ]);

        // DELETE
        $deleteResponse = $this->deleteJson('/api/siswa/' . $siswaId);
        $deleteResponse->assertOk()
            ->assertJsonPath('status', 'success');

        // Pastikan tidak ditemukan lagi di endpoint dan database
        $this->getJson('/api/siswa/' . $siswaId)->assertNotFound();
        $this->assertDatabaseMissing('siswa', ['id' => $siswaId]);
    }

    /**
     * 2. Verifikasi CRUD Orang Tua, Wali, Media Sosial, & Bantuan Siswa
     */
    public function test_family_social_and_aid_crud_lifecycle_and_database_persistence(): void
    {
        $admin = $this->account('administrator');
        $this->signIn($admin);
        $student = $this->student();

        // 2a. Wali: Create & Update
        $waliPayload = [
            'nama' => 'Hartono',
            'hubungan' => 'Paman',
            'pekerjaan' => 'Wiraswasta',
            'penghasilan' => 5000000,
            'alamat' => 'Jl. Bandung Malang',
            'kontak_wa' => '081234567890',
        ];
        $this->postJson("/api/siswa/{$student->id}/wali", $waliPayload)
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.nama', 'Hartono');

        $this->assertDatabaseHas('wali', [
            'siswa_id' => $student->id,
            'nama' => 'Hartono',
            'hubungan' => 'Paman',
            'pekerjaan' => 'Wiraswasta',
        ]);

        $this->getJson("/api/siswa/{$student->id}/wali")
            ->assertOk()
            ->assertJsonPath('data.nama', 'Hartono');

        // 2b. Orang Tua: Ayah & Ibu
        $ayahPayload = [
            'jenis_orang_tua' => 'ayah',
            'nama' => 'Bambang',
            'kondisi' => 'hidup',
            'pekerjaan' => 'PNS',
            'penghasilan' => 7000000,
            'alamat' => 'Jl. Bandung Malang',
            'kontak_wa' => '081234567891',
        ];
        $this->postJson("/api/siswa/{$student->id}/orang-tua", $ayahPayload)
            ->assertOk()
            ->assertJsonPath('data.nama', 'Bambang');

        $this->assertDatabaseHas('orang_tua', [
            'siswa_id' => $student->id,
            'jenis_orang_tua' => 'ayah',
            'nama' => 'Bambang',
            'kondisi' => 'hidup',
        ]);

        $ibuPayload = [
            'jenis_orang_tua' => 'ibu',
            'nama' => 'Siti',
            'kondisi' => 'hidup',
            'pekerjaan' => 'Guru',
            'penghasilan' => 4500000,
            'alamat' => 'Jl. Bandung Malang',
            'kontak_wa' => '081234567892',
        ];
        $this->postJson("/api/siswa/{$student->id}/orang-tua", $ibuPayload)
            ->assertOk()
            ->assertJsonPath('data.nama', 'Siti');

        $this->assertDatabaseHas('orang_tua', [
            'siswa_id' => $student->id,
            'jenis_orang_tua' => 'ibu',
            'nama' => 'Siti',
        ]);

        $this->getJson("/api/siswa/{$student->id}/orang-tua")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        // 2c. Media Sosial: Create, Read, Update, Delete
        $medsosCreate = $this->postJson("/api/siswa/{$student->id}/media-sosial", [
            'platform' => 'Instagram',
            'username' => 'siswa_malang_test',
        ])->assertCreated();

        $medsosId = $medsosCreate->json('data.id');
        $this->assertDatabaseHas('media_sosial', [
            'id' => $medsosId,
            'siswa_id' => $student->id,
            'jenis_media' => 'Instagram',
            'username' => 'siswa_malang_test',
        ]);

        $this->getJson("/api/siswa/{$student->id}/media-sosial")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Update Media Sosial
        $this->putJson("/api/siswa/{$student->id}/keluarga/media-sosial/{$medsosId}", [
            'platform' => 'Twitter / X',
            'username' => 'siswa_x_test',
        ])->assertOk();

        $this->assertDatabaseHas('media_sosial', [
            'id' => $medsosId,
            'jenis_media' => 'Twitter / X',
            'username' => 'siswa_x_test',
        ]);

        // Delete Media Sosial
        $this->deleteJson("/api/siswa/{$student->id}/keluarga/media-sosial/{$medsosId}")->assertOk();
        $this->assertDatabaseMissing('media_sosial', ['id' => $medsosId]);

        // 2d. Bantuan Siswa: Create, Read, Update, Delete
        $aidType = JenisBantuan::create(['nama_bantuan' => 'KIP Sekolah']);

        $aidCreate = $this->postJson("/api/siswa/{$student->id}/bantuan", [
            'jenis_bantuan_id' => $aidType->id,
            'nomor_penerima' => 'KIP-2026-99',
            'aktif' => true,
        ])->assertCreated();

        $aidId = $aidCreate->json('data.id');
        $this->assertDatabaseHas('bantuan_siswa', [
            'id' => $aidId,
            'siswa_id' => $student->id,
            'jenis_bantuan_id' => $aidType->id,
            'nomor_penerima' => 'KIP-2026-99',
            'aktif' => true,
        ]);

        $this->getJson("/api/siswa/{$student->id}/bantuan")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Update Bantuan
        $this->putJson("/api/siswa/{$student->id}/keluarga/bantuan/{$aidId}", [
            'jenis_bantuan_id' => $aidType->id,
            'nomor_penerima' => 'KIP-2026-REVISI',
            'aktif' => false,
        ])->assertOk();

        $this->assertDatabaseHas('bantuan_siswa', [
            'id' => $aidId,
            'nomor_penerima' => 'KIP-2026-REVISI',
            'aktif' => false,
        ]);

        // Delete Bantuan
        $this->deleteJson("/api/siswa/{$student->id}/keluarga/bantuan/{$aidId}")->assertOk();
        $this->assertDatabaseMissing('bantuan_siswa', ['id' => $aidId]);
    }

    /**
     * 3. Verifikasi CRUD Referensi Data Master (ReferenceController)
     */
    public function test_all_reference_types_crud_and_database_persistence(): void
    {
        $admin = $this->account('administrator');
        $this->signIn($admin);

        $references = [
            'kelas' => [
                'table' => 'kelas',
                'field' => 'nama_kelas',
                'payload' => ['nama_kelas' => 'XII-MIPA-1', 'tingkat' => 12, 'tahun_ajaran' => '2026/2027'],
                'updated' => ['nama_kelas' => 'XII-MIPA-1-Unggulan', 'tingkat' => 12, 'tahun_ajaran' => '2026/2027'],
            ],
            'mata-pelajaran' => [
                'table' => 'mata_pelajaran',
                'field' => 'nama_mapel',
                'payload' => ['nama_mapel' => 'Fisika Kuantum', 'jenis_mapel' => 'A'],
                'updated' => ['nama_mapel' => 'Fisika Terapan', 'jenis_mapel' => 'A'],
            ],
            'jenis-bantuan' => [
                'table' => 'jenis_bantuan',
                'field' => 'nama_bantuan',
                'payload' => ['nama_bantuan' => 'Beasiswa Daerah', 'keterangan' => 'Pemda Malang'],
                'updated' => ['nama_bantuan' => 'Beasiswa Daerah Revisi', 'keterangan' => 'Pemda Jawa Timur'],
            ],
            'hobi' => [
                'table' => 'hobi',
                'field' => 'nama_hobi',
                'payload' => ['nama_hobi' => 'Robotika'],
                'updated' => ['nama_hobi' => 'Robotika & AI'],
            ],
            'cita-cita' => [
                'table' => 'cita_cita',
                'field' => 'nama_cita_cita',
                'payload' => ['nama_cita_cita' => 'AI Engineer'],
                'updated' => ['nama_cita_cita' => 'Machine Learning Scientist'],
            ],
            'perguruan-tinggi' => [
                'table' => 'perguruan_tinggi',
                'field' => 'nama_pt',
                'payload' => ['nama_pt' => 'Institut Teknologi Malang', 'jenis_pt' => 'Negeri'],
                'updated' => ['nama_pt' => 'Universitas Teknologi Malang', 'jenis_pt' => 'Negeri'],
            ],
        ];

        foreach ($references as $type => $config) {
            $basePath = '/api/referensi/' . $type;

            // CREATE
            $createRes = $this->postJson($basePath, $config['payload'])->assertCreated();
            $id = $createRes->json('data.id');
            $this->assertNotNull($id);

            // Verifikasi di database
            $this->assertDatabaseHas($config['table'], ['id' => $id, $config['field'] => $config['payload'][$config['field']]]);

            // READ
            $readRes = $this->getJson($basePath)->assertOk();
            $items = collect($readRes->json('data'));
            $this->assertTrue($items->contains('id', $id));

            // UPDATE
            $this->putJson($basePath . '/' . $id, $config['updated'])->assertOk()
                ->assertJsonPath('data.' . $config['field'], $config['updated'][$config['field']]);

            // Verifikasi update di database
            $this->assertDatabaseHas($config['table'], ['id' => $id, $config['field'] => $config['updated'][$config['field']]]);

            // DELETE
            $this->deleteJson($basePath . '/' . $id)->assertOk();

            // Verifikasi terhapus dari database
            $this->assertDatabaseMissing($config['table'], ['id' => $id]);
        }
    }

    /**
     * 4. Verifikasi CRUD Basis Data Beasiswa (BasisDataBeasiswaController)
     */
    public function test_scholarship_catalog_crud_and_database_persistence(): void
    {
        $admin = $this->account('administrator');
        $this->signIn($admin);

        // CREATE
        $payload = [
            'nama_beasiswa' => 'Beasiswa Prestasi Unggulan 2026',
            'penyelenggara' => 'Kementerian Pendidikan',
            'deskripsi' => 'Beasiswa penuh untuk siswa berprestasi',
            'jenjang' => 'Kuliah',
            'durasi' => 8,
            'satuan_durasi' => 'semester',
            'periode_apply' => 'Semester Gasal',
            'kriteria' => [
                [
                    'nama_kriteria' => 'TKA Minimal',
                    'tipe_kriteria' => 'nilai_tka',
                    'operator' => '>=',
                    'nilai_kriteria' => '85',
                    'bobot' => 1.0,
                ],
            ],
        ];

        $createRes = $this->postJson('/api/beasiswa', $payload)->assertCreated();
        $beasiswaId = $createRes->json('data.id');

        $this->assertDatabaseHas('basis_data_beasiswa', [
            'id' => $beasiswaId,
            'nama_beasiswa' => 'Beasiswa Prestasi Unggulan 2026',
            'penyelenggara' => 'Kementerian Pendidikan',
            'jenjang' => 'Kuliah',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('kriteria_kelayakan_beasiswa', [
            'beasiswa_id' => $beasiswaId,
            'tipe_kriteria' => 'nilai_tka',
            'nilai_kriteria' => '85',
        ]);

        // READ (List & Detail)
        $this->getJson('/api/beasiswa?search=Prestasi')->assertOk()
            ->assertJsonPath('data.0.id', $beasiswaId);
        $this->getJson('/api/beasiswa/' . $beasiswaId)->assertOk()
            ->assertJsonPath('data.nama_beasiswa', 'Beasiswa Prestasi Unggulan 2026');

        // UPDATE
        $updatePayload = [
            'nama_beasiswa' => 'Beasiswa Prestasi Unggulan (Revisi 2026)',
            'penyelenggara' => 'Kemendikbudristek',
            'jenjang' => 'Kuliah',
            'durasi' => 8,
            'satuan_durasi' => 'semester',
            'kriteria' => [
                [
                    'nama_kriteria' => 'TKA Baru',
                    'tipe_kriteria' => 'nilai_tka',
                    'operator' => '>=',
                    'nilai_kriteria' => '90',
                    'bobot' => 1.0,
                ],
            ],
        ];
        $this->putJson('/api/beasiswa/' . $beasiswaId, $updatePayload)->assertOk()
            ->assertJsonPath('data.nama_beasiswa', 'Beasiswa Prestasi Unggulan (Revisi 2026)');

        $this->assertDatabaseHas('basis_data_beasiswa', [
            'id' => $beasiswaId,
            'nama_beasiswa' => 'Beasiswa Prestasi Unggulan (Revisi 2026)',
            'penyelenggara' => 'Kemendikbudristek',
        ]);
        $this->assertDatabaseHas('kriteria_kelayakan_beasiswa', [
            'beasiswa_id' => $beasiswaId,
            'nilai_kriteria' => '90',
        ]);

        // ARCHIVE (DELETE)
        $this->deleteJson('/api/beasiswa/' . $beasiswaId)->assertOk();
        $this->assertDatabaseHas('basis_data_beasiswa', [
            'id' => $beasiswaId,
            'is_active' => false,
        ]);

        // ACTIVATE (RESTORE)
        $this->postJson('/api/beasiswa/' . $beasiswaId . '/activate')->assertOk();
        $this->assertDatabaseHas('basis_data_beasiswa', [
            'id' => $beasiswaId,
            'is_active' => true,
        ]);
    }

    /**
     * 5. Verifikasi CRUD Student Support: Rapor, Presensi, Karakter, Organisasi, Saudara, Prestasi, Minat & Kelas
     */
    public function test_student_support_crud_and_database_persistence(): void
    {
        $admin = $this->account('administrator');
        $this->signIn($admin);
        $student = $this->student();

        // 5a. Nilai Rapor
        $mapel = MataPelajaran::create(['nama_mapel' => 'Biologi Molekuler', 'jenis_mapel' => 'A']);
        $this->putJson("/api/siswa/{$student->id}/nilai-rapor", [
            'semester' => 1,
            'nilai' => [
                ['mata_pelajaran_id' => $mapel->id, 'nilai_akhir' => 95.5],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('nilai_rapor', [
            'siswa_id' => $student->id,
            'mata_pelajaran_id' => $mapel->id,
            'semester' => 1,
            'nilai_akhir' => 95.5,
        ]);

        // 5b. Presensi
        $this->putJson("/api/siswa/{$student->id}/presensi", [
            'semester' => 1,
            'jumlah_hadir' => 95,
            'jumlah_sakit' => 3,
            'jumlah_izin' => 2,
            'jumlah_alpa' => 0,
        ])->assertOk();

        $this->assertDatabaseHas('rekap_presensi', [
            'siswa_id' => $student->id,
            'semester' => 1,
            'jumlah_hadir' => 95,
            'persentase_kehadiran' => 95.0,
        ]);

        // 5c. Karakter: Create, Update, Delete
        $karakterCreate = $this->postJson("/api/siswa/{$student->id}/pendukung/karakter", [
            'semester' => 1,
            'jenis_catatan' => 'Kedisiplinan',
            'poin_disiplin' => 5,
            'deskripsi' => 'Siswa teladan',
            'tanggal_pencatatan' => today()->toDateString(),
        ])->assertCreated();
        $karakterId = $karakterCreate->json('data.id');

        $this->assertDatabaseHas('catatan_karakter', [
            'id' => $karakterId,
            'siswa_id' => $student->id,
            'jenis_catatan' => 'Kedisiplinan',
            'poin_disiplin' => 5,
        ]);

        $this->putJson("/api/siswa/{$student->id}/pendukung/karakter/{$karakterId}", [
            'semester' => 1,
            'jenis_catatan' => 'Kedisiplinan',
            'poin_disiplin' => 10,
            'deskripsi' => 'Siswa teladan berprestasi',
            'tanggal_pencatatan' => today()->toDateString(),
        ])->assertOk();

        $this->assertDatabaseHas('catatan_karakter', [
            'id' => $karakterId,
            'poin_disiplin' => 10,
        ]);

        $this->deleteJson("/api/siswa/{$student->id}/pendukung/karakter/{$karakterId}")->assertOk();
        $this->assertDatabaseMissing('catatan_karakter', ['id' => $karakterId]);

        // 5d. Organisasi: Create, Update, Delete
        $orgCreate = $this->postJson("/api/siswa/{$student->id}/pendukung/organisasi", [
            'jenis_organisasi' => 'Sekolah',
            'nama_organisasi' => 'Pramuka SMAN 8',
            'jabatan' => 'Pradana',
            'bobot_jabatan' => 80.0,
            'periode_jabatan' => '2025/2026',
        ])->assertCreated();
        $orgId = $orgCreate->json('data.id');

        $this->assertDatabaseHas('riwayat_organisasi', [
            'id' => $orgId,
            'nama_organisasi' => 'Pramuka SMAN 8',
            'jabatan' => 'Pradana',
        ]);

        $this->putJson("/api/siswa/{$student->id}/pendukung/organisasi/{$orgId}", [
            'jenis_organisasi' => 'Sekolah',
            'nama_organisasi' => 'Pramuka SMAN 8 Malang',
            'jabatan' => 'Pradana Utama',
            'bobot_jabatan' => 90.0,
            'periode_jabatan' => '2025/2026',
        ])->assertOk();

        $this->assertDatabaseHas('riwayat_organisasi', [
            'id' => $orgId,
            'jabatan' => 'Pradana Utama',
        ]);

        $this->deleteJson("/api/siswa/{$student->id}/pendukung/organisasi/{$orgId}")->assertOk();
        $this->assertDatabaseMissing('riwayat_organisasi', ['id' => $orgId]);

        // 5e. Prestasi: Create, Update, Delete
        $prestasiCreate = $this->postJson("/api/siswa/{$student->id}/pendukung/prestasi", [
            'jenis_prestasi' => 'Akademik',
            'nama_kompetisi' => 'OSN Fisika',
            'bidang' => 'Fisika',
            'tingkat' => 'Nasional',
            'peringkat' => 'Juara 1',
            'status_verifikasi' => 'terverifikasi',
            'penyelenggara' => 'Puspresnas',
            'tahun' => 2026,
        ])->assertCreated();
        $prestasiId = $prestasiCreate->json('data.id');

        $this->assertDatabaseHas('prestasi_murid', [
            'id' => $prestasiId,
            'nama_kompetisi' => 'OSN Fisika',
            'tingkat' => 'Nasional',
            'peringkat' => 'Juara 1',
        ]);

        $this->putJson("/api/siswa/{$student->id}/pendukung/prestasi/{$prestasiId}", [
            'jenis_prestasi' => 'Akademik',
            'nama_kompetisi' => 'OSN Fisika Terbuka',
            'bidang' => 'Fisika',
            'tingkat' => 'Nasional',
            'peringkat' => 'Medali Emas',
            'status_verifikasi' => 'terverifikasi',
            'penyelenggara' => 'Puspresnas',
            'tahun' => 2026,
        ])->assertOk();

        $this->assertDatabaseHas('prestasi_murid', [
            'id' => $prestasiId,
            'peringkat' => 'Medali Emas',
        ]);

        $this->deleteJson("/api/siswa/{$student->id}/pendukung/prestasi/{$prestasiId}")->assertOk();
        $this->assertDatabaseMissing('prestasi_murid', ['id' => $prestasiId]);

        // 5f. Kelas & Minat
        $kelas = Kelas::create(['nama_kelas' => 'XII-MIPA-5', 'tingkat' => 12, 'tahun_ajaran' => '2026/2027']);
        $this->postJson("/api/siswa/{$student->id}/kelas", [
            'kelas_id' => $kelas->id,
            'tanggal_mulai' => today()->toDateString(),
        ])->assertCreated();

        $this->assertDatabaseHas('riwayat_kelas', [
            'siswa_id' => $student->id,
            'kelas_id' => $kelas->id,
        ]);

        $hobi = Hobi::create(['nama_hobi' => 'Menulis']);
        $cita = CitaCita::create(['nama_cita_cita' => 'Dosen']);
        $this->putJson("/api/siswa/{$student->id}/minat", [
            'hobi' => [$hobi->id],
            'cita_cita' => [$cita->id],
        ])->assertOk();

        $this->assertDatabaseHas('hobi_siswa', ['siswa_id' => $student->id, 'hobi_id' => $hobi->id]);
        $this->assertDatabaseHas('cita_cita_siswa', ['siswa_id' => $student->id, 'cita_cita_id' => $cita->id]);
    }

    /**
     * 6. Verifikasi CRUD Program Eligibilitas & Klaster Jurusan
     */
    public function test_program_eligibility_and_major_cluster_crud_and_database_persistence(): void
    {
        $admin = $this->account('administrator');
        $this->signIn($admin);

        // 6a. Program Eligibilitas
        $eligPayload = [
            'nama' => 'SNBP Kuota Sekolah 2027',
            'deskripsi' => 'Program seleksi eligibilitas SNBP',
            'kriteria' => [
                ['nama_kriteria' => 'TKA Minimal', 'tipe_kriteria' => 'nilai_tka', 'operator' => '>=', 'nilai_kriteria' => '80'],
            ],
            'is_active' => true,
        ];

        $eligCreate = $this->postJson('/api/program-eligibilitas', $eligPayload)->assertCreated();
        $eligId = $eligCreate->json('data.id');

        $this->assertDatabaseHas('program_eligibilitas', [
            'id' => $eligId,
            'nama' => 'SNBP Kuota Sekolah 2027',
            'version' => 1,
            'is_active' => true,
        ]);

        $this->getJson('/api/program-eligibilitas')->assertOk();

        // Update versioning
        $this->putJson('/api/program-eligibilitas/' . $eligId, [
            'nama' => 'SNBP Kuota Sekolah 2027 Revisi',
            'kriteria' => [
                ['nama_kriteria' => 'TKA Minimal', 'tipe_kriteria' => 'nilai_tka', 'operator' => '>=', 'nilai_kriteria' => '85'],
            ],
        ])->assertOk()->assertJsonPath('data.version', 2);

        $this->assertDatabaseHas('program_eligibilitas', [
            'id' => $eligId,
            'version' => 2,
            'nama' => 'SNBP Kuota Sekolah 2027 Revisi',
        ]);

        // Archive
        $this->deleteJson('/api/program-eligibilitas/' . $eligId)->assertOk();
        $this->assertDatabaseHas('program_eligibilitas', [
            'id' => $eligId,
            'is_active' => false,
        ]);

        // 6b. Klaster Jurusan
        $mapel = MataPelajaran::create(['nama_mapel' => 'Matematika Tingkat Lanjut', 'jenis_mapel' => 'A']);
        $klasterPayload = [
            'nama_klaster' => 'Teknik & Rekayasa Komputer',
            'deskripsi' => 'Jurusan rumpun ilmu komputasi dan teknik',
            'kode_riasec_relevan' => 'RIA',
            'bobot_komponen' => [
                'nilai' => 50,
                'riasec' => 50,
                'multiple_intelligence' => 0,
            ],
            'mapel' => [
                ['mata_pelajaran_id' => $mapel->id, 'bobot_persentase' => 100],
            ],
        ];

        $klasterCreate = $this->postJson('/api/klaster-jurusan', $klasterPayload)->assertCreated();
        $klasterId = $klasterCreate->json('data.id');

        $this->assertDatabaseHas('klaster_prodi', [
            'id' => $klasterId,
            'nama_klaster' => 'Teknik & Rekayasa Komputer',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('bobot_mapel_klaster', [
            'klaster_id' => $klasterId,
            'mata_pelajaran_id' => $mapel->id,
            'bobot_persentase' => 100,
        ]);

        $this->getJson('/api/klaster-jurusan')->assertOk();

        // Update
        $this->putJson('/api/klaster-jurusan/' . $klasterId, [
            ...$klasterPayload,
            'nama_klaster' => 'Ilmu Komputer & Sistem Informasi',
        ])->assertOk();

        $this->assertDatabaseHas('klaster_prodi', [
            'id' => $klasterId,
            'nama_klaster' => 'Ilmu Komputer & Sistem Informasi',
        ]);

        // Archive
        $this->deleteJson('/api/klaster-jurusan/' . $klasterId)->assertOk();
        $this->assertDatabaseHas('klaster_prodi', [
            'id' => $klasterId,
            'is_active' => false,
        ]);
    }

    /**
     * 7. Verifikasi CRUD Study Tracer & Alumni College Progress
     */
    public function test_study_tracer_and_alumni_progress_crud_and_database_persistence(): void
    {
        $admin = $this->account('administrator');
        $this->signIn($admin);
        $alumni = $this->student(null, ['tahun_lulus' => 2025]);
        $pt = PerguruanTinggi::create(['nama_pt' => 'Institut Teknologi Sepuluh Nopember', 'jenis_pt' => 'Negeri']);

        // CREATE Study Tracer (Admission)
        $tracerPayload = [
            'siswa_id' => $alumni->id,
            'perguruan_tinggi_id' => $pt->id,
            'strata_pendidikan' => 'S1',
            'program_studi' => 'Teknik Informatika',
            'jalur_masuk' => 'SNBP',
            'nilai_rapor_pendaftaran' => 92.5,
            'pilihan_ke' => 1,
            'status_penerimaan' => 'diterima',
            'tahun_masuk_pt' => 2025,
            'kontak_wa_terbaru' => '081234567899',
        ];

        $tracerCreate = $this->postJson('/api/study-tracer', $tracerPayload)->assertCreated();
        $tracerId = $tracerCreate->json('data.id');

        $this->assertDatabaseHas('study_tracer', [
            'id' => $tracerId,
            'siswa_id' => $alumni->id,
            'perguruan_tinggi_id' => $pt->id,
            'program_studi' => 'Teknik Informatika',
            'status_penerimaan' => 'diterima',
        ]);

        // READ
        $this->getJson('/api/study-tracer/' . $tracerId)->assertOk()
            ->assertJsonPath('data.program_studi', 'Teknik Informatika');

        // UPDATE Study Tracer
        $this->putJson('/api/study-tracer/' . $tracerId, [
            ...$tracerPayload,
            'program_studi' => 'Sains Data',
        ])->assertOk();

        $this->assertDatabaseHas('study_tracer', [
            'id' => $tracerId,
            'program_studi' => 'Sains Data',
        ]);

        // PUBLISH STUDY TRACER
        $this->patchJson('/api/study-tracer/' . $tracerId . '/publikasi', [
            'is_published' => true,
            'consent_reference' => 'Surat persetujuan siswa 2025/01',
            'persetujuan_dikonfirmasi' => true,
        ])->assertOk();

        $this->assertDatabaseHas('study_tracer', [
            'id' => $tracerId,
            'izinkan_tampil_di_buku' => true,
        ]);

        // CREATE ALUMNI COLLEGE PROGRESS
        $progressPayload = [
            'periode' => 'Ganjil 2025/2026',
            'semester' => 1,
            'status_pendidikan' => 'aktif',
            'tanggal_update' => now('Asia/Jakarta')->toDateString(),
            'ipk_terkini' => 3.85,
            'nominal_ukt' => 5000000,
            'nominal_ipi' => null,
            'penerima_kipk' => false,
            'beasiswa' => [],
        ];

        $progressCreate = $this->postJson("/api/alumni/{$tracerId}/college-progress", $progressPayload)->assertCreated();
        $progressId = $progressCreate->json('data.id');

        $this->assertDatabaseHas('alumni_progress', [
            'id' => $progressId,
            'study_tracer_id' => $tracerId,
            'semester' => 1,
            'status_pendidikan' => 'aktif',
            'ipk_terkini' => 3.85,
            'nominal_ukt' => 5000000,
            'is_current' => true,
        ]);

        // UPDATE ALUMNI COLLEGE PROGRESS (Version superseding)
        $progressUpdatePayload = [
            ...$progressPayload,
            'status_pendidikan' => 'cuti',
            'ipk_terkini' => 3.80,
        ];
        $progressUpdate = $this->putJson("/api/alumni/{$tracerId}/college-progress/{$progressId}", $progressUpdatePayload)->assertCreated();
        $newProgressId = $progressUpdate->json('data.id');

        $this->assertDatabaseHas('alumni_progress', [
            'id' => $newProgressId,
            'status_pendidikan' => 'cuti',
            'supersedes_id' => $progressId,
            'is_current' => true,
        ]);
        $this->assertDatabaseHas('alumni_progress', [
            'id' => $progressId,
            'is_current' => false,
        ]);

        // ARCHIVE ALUMNI COLLEGE PROGRESS
        $this->deleteJson("/api/alumni/{$tracerId}/college-progress/{$newProgressId}")->assertOk();
        $this->assertDatabaseHas('alumni_progress', [
            'id' => $newProgressId,
            'is_current' => false,
        ]);
    }

    /**
     * 8. Verifikasi CRUD Rekomendasi Beasiswa & Detail
     */
    public function test_scholarship_recommendation_crud_and_database_persistence(): void
    {
        $admin = $this->account('administrator');
        $this->signIn($admin);
        $student = $this->student(null, ['nilai_tka' => 92]);
        $this->classroom($student);

        $beasiswa = BasisDataBeasiswa::create([
            'nama_beasiswa' => 'Beasiswa Indonesia Maju',
            'jenjang' => 'SMA',
            'is_active' => true,
        ]);
        $beasiswa->kriteriaKelayakanBeasiswa()->create([
            'nama_kriteria' => 'TKA Minimal',
            'tipe_kriteria' => 'nilai_tka',
            'operator' => '>=',
            'nilai_kriteria' => '80',
            'bobot' => 1.0,
        ]);

        $beasiswa2 = BasisDataBeasiswa::create([
            'nama_beasiswa' => 'Beasiswa Prestasi Kedua',
            'jenjang' => 'SMA',
            'is_active' => true,
        ]);
        $beasiswa2->kriteriaKelayakanBeasiswa()->create([
            'nama_kriteria' => 'TKA Minimal',
            'tipe_kriteria' => 'nilai_tka',
            'operator' => '>=',
            'nilai_kriteria' => '80',
            'bobot' => 1.0,
        ]);

        $this->mock(GeminiScholarshipExplainer::class, fn ($m) => $m->shouldNotReceive('explain'));

        // SAVE Rekomendasi
        $saveRes = $this->postJson("/api/siswa/{$student->id}/rekomendasi-beasiswa/save", ['target_jenjang' => 'SMA'])->assertOk();
        $rekId = $saveRes->json('data.id');

        $this->assertDatabaseHas('rekomendasi_beasiswa', [
            'id' => $rekId,
            'siswa_id' => $student->id,
            'target_jenjang' => 'SMA',
        ]);
        $this->assertDatabaseHas('rekomendasi_beasiswa_detail', [
            'rekomendasi_beasiswa_id' => $rekId,
            'beasiswa_id' => $beasiswa->id,
        ]);

        $detailId1 = RekomendasiBeasiswaDetail::where('rekomendasi_beasiswa_id', $rekId)->where('beasiswa_id', $beasiswa->id)->value('id');
        $detailId2 = RekomendasiBeasiswaDetail::where('rekomendasi_beasiswa_id', $rekId)->where('beasiswa_id', $beasiswa2->id)->value('id');

        // UPDATE DETAIL 1 (Revision moves 1 -> 2)
        $this->patchJson("/api/rekomendasi-beasiswa/{$rekId}/detail/{$detailId1}", [
            'siswa_id' => $student->id,
            'revision' => 1,
            'alasan_rekomendasi' => 'Siswa sangat potensial pada bidang sains',
            'waktu_disarankan' => 'November 2026',
        ])->assertOk();

        $this->assertDatabaseHas('rekomendasi_beasiswa_detail', [
            'id' => $detailId1,
            'alasan_rekomendasi' => 'Siswa sangat potensial pada bidang sains',
            'waktu_disarankan' => 'November 2026',
        ]);

        // DELETE DETAIL 2 (Soft Delete, Revision moves 2 -> 3)
        $this->deleteJson("/api/rekomendasi-beasiswa/{$rekId}/detail/{$detailId2}", [
            'siswa_id' => $student->id,
            'revision' => 2,
        ])->assertOk();

        $this->assertSoftDeleted('rekomendasi_beasiswa_detail', ['id' => $detailId2]);

        // REVIEW / APPROVE (Revision moves 3 -> 4)
        $this->patchJson("/api/rekomendasi-beasiswa/{$rekId}/review", [
            'siswa_id' => $student->id,
            'revision' => 3,
            'status_review' => 'disetujui',
        ])->assertOk();

        $this->assertDatabaseHas('rekomendasi_beasiswa', [
            'id' => $rekId,
            'status_review' => 'disetujui',
        ]);
    }

    /**
     * 9. Verifikasi Pengajuan Profil Siswa (Student Submission & Admin Review)
     */
    public function test_student_initial_profile_submission_and_admin_review_database_persistence(): void
    {
        $studentUser = $this->account('siswa');
        $student = $this->student($studentUser);
        $hobi = Hobi::create(['nama_hobi' => 'Catur']);

        // Siswa login dan kirim submission
        $this->signIn($studentUser);
        $submissionRes = $this->postJson('/api/profil-siswa/saya/submission', [
            'nama_panggilan' => 'Bambang Uji',
            'alamat_rumah' => 'Jl. Kawi Malang',
            'hobi' => [$hobi->id],
        ])->assertCreated();

        $submissionId = $submissionRes->json('data.id');
        $this->assertDatabaseHas('student_profile_submissions', [
            'id' => $submissionId,
            'siswa_id' => $student->id,
            'status' => 'pending',
        ]);

        // Admin login dan review (Approve)
        $admin = $this->account('administrator');
        $this->signIn($admin);

        $this->getJson('/api/profile-submissions/' . $submissionId)->assertOk();

        $this->patchJson('/api/profile-submissions/' . $submissionId . '/review', [
            'status' => 'approved',
        ])->assertOk();

        // Verifikasi status submission disetujui & data masuk ke tabel siswa
        $this->assertDatabaseHas('student_profile_submissions', [
            'id' => $submissionId,
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('siswa', [
            'id' => $student->id,
            'nama_panggilan' => 'Bambang Uji',
            'alamat_rumah' => 'Jl. Kawi Malang',
        ]);
        $this->assertDatabaseHas('hobi_siswa', [
            'siswa_id' => $student->id,
            'hobi_id' => $hobi->id,
        ]);
    }
}

# Skema lengkap Eightracer B4

Kandidat 2 November 2026: 62 tabel, 515 kolom. Tidak berisi baris data murid. Tabel baseline memakai tipe MySQL aktual; empat tabel baru memakai metadata hasil migrasi SQLite terisolasi, dengan tipe MySQL otoritatif tersedia pada DDL referensi dan migration Laravel. Relasi/index di bawah berasal dari hasil migration kandidat.

Aplikasi mengenkripsi school_import_batches.plan dan student_profile_submissions.payload sebelum penyimpanan. Password di-hash, token autentikasi disimpan hash oleh Sanctum. Jangan menggunakan kolom JSON sebagai alasan untuk menerima field bebas dari klien.

Pemetaan entitas konseptual dan makna versi/progres: lihat b4-arsitektur.md.

## administrator

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| user_id | bigint(20) unsigned | NO | — | UNI |
| nip | varchar(255) | YES | NULL | — |
| nama_lengkap | varchar(255) | NO | — | — |

Foreign key:
- `user_id -> users.id`; delete CASCADE, update NO ACTION.

Index:
- `administrator_user_id_unique` (user_id); UNIQUE.

## alumni_progress

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| siswa_id | bigint(20) unsigned | NO | — | MUL |
| study_tracer_id | bigint(20) unsigned | YES | NULL | MUL |
| tahun_lulus | varchar(255) | NO | — | — |
| status_pendidikan | varchar(255) | YES | NULL | — |
| ipk_terkini | decimal(3,2) | YES | NULL | — |
| tanggal_update | date | NO | — | — |
| kategori_ekonomi | varchar(30) | YES | NULL | — |
| referensi_ekonomi | varchar(500) | YES | NULL | — |
| periode | varchar(50) | YES | NULL | — |
| semester | tinyint(3) unsigned | YES | NULL | — |
| penerima_kipk | tinyint(1) | YES | NULL | — |
| nominal_ukt | decimal(15,2) | YES | NULL | — |
| nominal_ipi | decimal(15,2) | YES | NULL | — |
| is_published | tinyint(1) | NO | 0 | — |
| publish_financials | tinyint(1) | NO | 0 | — |
| consent_reference | varchar(500) | YES | NULL | — |
| consent_at | timestamp | YES | NULL | — |
| consent_by | bigint(20) unsigned | YES | NULL | MUL |
| is_current | tinyint(1) | NO | 1 | — |
| revision | smallint(5) unsigned | NO | 1 | — |
| supersedes_id | bigint(20) unsigned | YES | NULL | — |

Foreign key:
- `consent_by -> users.id`; delete SET NULL, update NO ACTION.
- `siswa_id -> siswa.id`; delete CASCADE, update NO ACTION.
- `study_tracer_id -> study_tracer.id`; delete SET NULL, update NO ACTION.

Index:
- `progress_latest_student` (siswa_id, is_current, tanggal_update, id); non-unique.
- `progress_period_revision` (study_tracer_id, periode, revision); UNIQUE.

## alumni_progress_beasiswa

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| alumni_progress_id | bigint(20) unsigned | NO | — | MUL |
| beasiswa_id | bigint(20) unsigned | NO | — | MUL |
| status_beasiswa | enum('proses','diterima','ditolak') | NO | 'proses' | — |
| nominal_beasiswa | decimal(15,2) | YES | NULL | — |
| nominal_ukt | decimal(15,2) | YES | NULL | — |
| periode | varchar(255) | YES | NULL | — |
| beasiswa_snapshot | longtext | YES | NULL | — |

Foreign key:
- `beasiswa_id -> basis_data_beasiswa.id`; delete RESTRICT, update NO ACTION.
- `alumni_progress_id -> alumni_progress.id`; delete CASCADE, update NO ACTION.

Index:
- `award_program_status` (beasiswa_id, status_beasiswa, alumni_progress_id); non-unique.

## asesmen_karakteristik

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| siswa_id | bigint(20) unsigned | NO | — | MUL |
| tipe_kepribadian | varchar(255) | YES | NULL | — |
| kecerdasan_majemuk_dominan | varchar(255) | YES | NULL | — |
| kode_riasec | varchar(10) | YES | NULL | — |
| tanggal_asesmen | datetime | NO | — | — |
| gaya_belajar | varchar(50) | YES | NULL | — |
| iq | decimal(5,2) | YES | NULL | — |
| is_current | tinyint(1) | NO | 1 | — |
| supersedes_id | bigint(20) unsigned | YES | NULL | — |
| created_by | bigint(20) unsigned | YES | NULL | MUL |

Foreign key:
- `created_by -> users.id`; delete SET NULL, update NO ACTION.
- `siswa_id -> siswa.id`; delete CASCADE, update NO ACTION.

Index:
- Tidak ada index tambahan dalam introspeksi; PK mengikuti kolom.

## asesmen_multiple_intelligence

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| siswa_id | bigint(20) unsigned | NO | — | MUL |
| jenis_kecerdasan | varchar(255) | NO | — | — |
| skor | decimal(6,2) | NO | — | — |
| tanggal_asesmen | date | NO | — | — |
| asesmen_id | bigint(20) unsigned | YES | NULL | MUL |

Foreign key:
- `asesmen_id -> asesmen_karakteristik.id`; delete CASCADE, update NO ACTION.
- `siswa_id -> siswa.id`; delete CASCADE, update NO ACTION.

Index:
- Tidak ada index tambahan dalam introspeksi; PK mengikuti kolom.

## asesmen_riasec

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| siswa_id | bigint(20) unsigned | NO | — | MUL |
| realistic_score | decimal(6,2) | NO | 0.00 | — |
| investigative_score | decimal(6,2) | NO | 0.00 | — |
| artistic_score | decimal(6,2) | NO | 0.00 | — |
| social_score | decimal(6,2) | NO | 0.00 | — |
| enterprising_score | decimal(6,2) | NO | 0.00 | — |
| conventional_score | decimal(6,2) | NO | 0.00 | — |
| kode_hasil | varchar(10) | YES | NULL | — |
| tanggal_asesmen | date | NO | — | — |
| asesmen_id | bigint(20) unsigned | YES | NULL | MUL |

Foreign key:
- `asesmen_id -> asesmen_karakteristik.id`; delete CASCADE, update NO ACTION.
- `siswa_id -> siswa.id`; delete CASCADE, update NO ACTION.

Index:
- Tidak ada index tambahan dalam introspeksi; PK mengikuti kolom.

## aturan_rekomendasi_mapel

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| mata_pelajaran_id | bigint(20) unsigned | NO | — | MUL |
| indikator | varchar(255) | NO | — | — |
| nilai_indikator | varchar(255) | NO | — | — |
| bobot | decimal(5,2) | NO | — | — |
| sumber_panduan | varchar(255) | YES | NULL | — |
| keterangan | text | YES | NULL | — |
| aktif | tinyint(1) | NO | 1 | — |

Foreign key:
- `mata_pelajaran_id -> mata_pelajaran.id`; delete CASCADE, update NO ACTION.

Index:
- Tidak ada index tambahan dalam introspeksi; PK mengikuti kolom.

## auth_challenges

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | char(36) | NO | — | PRI |
| email | varchar(255) | NO | — | MUL |
| purpose | varchar(40) | NO | — | — |
| user_id | bigint(20) unsigned | YES | NULL | MUL |
| siswa_id | bigint(20) unsigned | YES | NULL | MUL |
| payload | text | NO | — | — |
| code_hash | varchar(64) | NO | — | — |
| attempts | tinyint(3) unsigned | NO | 0 | — |
| expires_at | datetime | NO | — | — |
| verified_at | datetime | YES | NULL | — |
| grant_hash | varchar(64) | YES | NULL | — |
| consumed_at | datetime | YES | NULL | — |
| created_at | datetime | NO | — | — |

Foreign key:
- `siswa_id -> siswa.id`; delete CASCADE, update NO ACTION.
- `user_id -> users.id`; delete CASCADE, update NO ACTION.

Index:
- `auth_challenges_email_purpose_created_at_index` (email, purpose, created_at); non-unique.
- `sqlite_autoindex_auth_challenges_1` (id); UNIQUE.

## bantuan_siswa

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| siswa_id | bigint(20) unsigned | NO | — | MUL |
| jenis_bantuan_id | bigint(20) unsigned | NO | — | MUL |
| nomor_penerima | varchar(255) | YES | NULL | — |
| tanggal_mulai | date | YES | NULL | — |
| tanggal_selesai | date | YES | NULL | — |
| aktif | tinyint(1) | NO | 1 | — |

Foreign key:
- `jenis_bantuan_id -> jenis_bantuan.id`; delete RESTRICT, update NO ACTION.
- `siswa_id -> siswa.id`; delete CASCADE, update NO ACTION.

Index:
- Tidak ada index tambahan dalam introspeksi; PK mengikuti kolom.

## basis_data_beasiswa

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| nama_beasiswa | varchar(255) | NO | — | — |
| penyelenggara | varchar(255) | YES | NULL | — |
| deskripsi | text | YES | NULL | — |
| jenjang | varchar(255) | YES | NULL | — |
| tanggal_buka | date | YES | NULL | — |
| tanggal_tutup | date | YES | NULL | — |
| link_pendaftaran | varchar(500) | YES | NULL | — |
| is_active | tinyint(1) | NO | 1 | — |
| durasi | decimal(5,2) | YES | NULL | — |
| satuan_durasi | varchar(15) | YES | NULL | — |
| periode_apply | varchar(255) | YES | NULL | — |
| program_eligibilitas_id | bigint(20) unsigned | YES | NULL | MUL |

Foreign key:
- `program_eligibilitas_id -> program_eligibilitas.id`; delete RESTRICT, update NO ACTION.

Index:
- Tidak ada index tambahan dalam introspeksi; PK mengikuti kolom.

## bobot_mapel_klaster

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| klaster_id | bigint(20) unsigned | NO | — | MUL |
| mata_pelajaran_id | bigint(20) unsigned | NO | — | MUL |
| bobot_persentase | decimal(5,2) | NO | — | — |

Foreign key:
- `mata_pelajaran_id -> mata_pelajaran.id`; delete RESTRICT, update NO ACTION.
- `klaster_id -> klaster_prodi.id`; delete CASCADE, update NO ACTION.

Index:
- `bobot_mapel_klaster_unique` (klaster_id, mata_pelajaran_id); UNIQUE.

## buku_alumni

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| tahun_lulusan | varchar(255) | NO | — | — |
| judul_buku | varchar(255) | NO | — | — |
| file_pdf_url | varchar(500) | YES | NULL | — |
| thumbnail_cover_url | varchar(500) | YES | NULL | — |
| is_published | tinyint(1) | NO | 0 | — |
| tanggal_rilis | date | YES | NULL | — |

Foreign key:
- Tidak ada FK deklaratif. Pointer audit polimorfik dibatasi service.

Index:
- Tidak ada index tambahan dalam introspeksi; PK mengikuti kolom.

## cache

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| key | varchar(255) | NO | — | PRI |
| value | mediumtext | NO | — | — |
| expiration | int(11) | NO | — | — |

Foreign key:
- Tidak ada FK deklaratif. Pointer audit polimorfik dibatasi service.

Index:
- `sqlite_autoindex_cache_1` (key); UNIQUE.

## cache_locks

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| key | varchar(255) | NO | — | PRI |
| owner | varchar(255) | NO | — | — |
| expiration | int(11) | NO | — | — |

Foreign key:
- Tidak ada FK deklaratif. Pointer audit polimorfik dibatasi service.

Index:
- `sqlite_autoindex_cache_locks_1` (key); UNIQUE.

## catatan_karakter

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| siswa_id | bigint(20) unsigned | NO | — | MUL |
| semester | int(11) | NO | — | — |
| jenis_catatan | varchar(255) | NO | — | — |
| poin_disiplin | int(11) | NO | 0 | — |
| deskripsi | text | YES | NULL | — |
| tanggal_pencatatan | datetime | NO | — | — |

Foreign key:
- `siswa_id -> siswa.id`; delete CASCADE, update NO ACTION.

Index:
- Tidak ada index tambahan dalam introspeksi; PK mengikuti kolom.

## cita_cita

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| nama_cita_cita | varchar(255) | NO | — | UNI |

Foreign key:
- Tidak ada FK deklaratif. Pointer audit polimorfik dibatasi service.

Index:
- `cita_cita_nama_cita_cita_unique` (nama_cita_cita); UNIQUE.

## cita_cita_siswa

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| siswa_id | bigint(20) unsigned | NO | — | MUL |
| cita_cita_id | bigint(20) unsigned | NO | — | MUL |
| prioritas | int(11) | NO | 1 | — |

Foreign key:
- `cita_cita_id -> cita_cita.id`; delete RESTRICT, update NO ACTION.
- `siswa_id -> siswa.id`; delete CASCADE, update NO ACTION.

Index:
- `cita_cita_siswa_siswa_id_cita_cita_id_unique` (siswa_id, cita_cita_id); UNIQUE.

## detail_skor_eligibilitas

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| hasil_id | bigint(20) unsigned | NO | — | UNI |
| skor_raw_rapor | decimal(8,4) | YES | NULL | — |
| skor_norm_rapor | decimal(8,4) | YES | NULL | — |
| skor_raw_tka | decimal(8,4) | YES | NULL | — |
| skor_norm_tka | decimal(8,4) | YES | NULL | — |
| skor_raw_prestasi | decimal(8,4) | YES | NULL | — |
| skor_norm_prestasi | decimal(8,4) | YES | NULL | — |
| skor_raw_presensi | decimal(8,4) | YES | NULL | — |
| skor_norm_presensi | decimal(8,4) | YES | NULL | — |
| skor_raw_karakter | decimal(8,4) | YES | NULL | — |
| skor_norm_karakter | decimal(8,4) | YES | NULL | — |
| skor_raw_organisasi | decimal(8,4) | YES | NULL | — |
| skor_norm_organisasi | decimal(8,4) | YES | NULL | — |

Foreign key:
- `hasil_id -> hasil_eligibilitas.id`; delete CASCADE, update NO ACTION.

Index:
- `detail_skor_eligibilitas_hasil_id_unique` (hasil_id); UNIQUE.

## failed_jobs

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| uuid | varchar(255) | NO | — | UNI |
| connection | text | NO | — | — |
| queue | text | NO | — | — |
| payload | longtext | NO | — | — |
| exception | longtext | NO | — | — |
| failed_at | timestamp | NO | current_timestamp() | — |

Foreign key:
- Tidak ada FK deklaratif. Pointer audit polimorfik dibatasi service.

Index:
- `failed_jobs_uuid_unique` (uuid); UNIQUE.

## guru

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| user_id | bigint(20) unsigned | NO | — | UNI |
| nip | varchar(255) | YES | NULL | — |
| nama_lengkap | varchar(255) | NO | — | — |

Foreign key:
- `user_id -> users.id`; delete CASCADE, update NO ACTION.

Index:
- `guru_user_id_unique` (user_id); UNIQUE.

## hasil_eligibilitas

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| siswa_id | bigint(20) unsigned | NO | — | MUL |
| periode_id | bigint(20) unsigned | NO | — | MUL |
| total_skor_akhir | decimal(8,4) | NO | — | — |
| peringkat | int(11) | YES | NULL | — |
| status_eligible | varchar(255) | YES | NULL | — |
| tanggal_kalkulasi | datetime | NO | — | — |

Foreign key:
- `periode_id -> periode_eligibilitas.id`; delete CASCADE, update NO ACTION.
- `siswa_id -> siswa.id`; delete CASCADE, update NO ACTION.

Index:
- `hasil_eligibilitas_siswa_id_periode_id_unique` (siswa_id, periode_id); UNIQUE.

## hasil_program_eligibilitas

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| siswa_id | bigint(20) unsigned | NO | — | MUL |
| program_id | bigint(20) unsigned | NO | — | MUL |
| program_version | int(10) unsigned | NO | — | — |
| status | varchar(40) | NO | — | — |
| skor | decimal(8,4) | NO | — | — |
| evaluasi | longtext | NO | — | — |
| source_fingerprint | varchar(64) | NO | — | — |
| created_at | timestamp | YES | NULL | — |
| updated_at | timestamp | YES | NULL | — |

Foreign key:
- `program_id -> program_eligibilitas.id`; delete RESTRICT, update NO ACTION.
- `siswa_id -> siswa.id`; delete CASCADE, update NO ACTION.

Index:
- Tidak ada index tambahan dalam introspeksi; PK mengikuti kolom.

## hobi

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| nama_hobi | varchar(255) | NO | — | UNI |

Foreign key:
- Tidak ada FK deklaratif. Pointer audit polimorfik dibatasi service.

Index:
- `hobi_nama_hobi_unique` (nama_hobi); UNIQUE.

## hobi_siswa

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| siswa_id | bigint(20) unsigned | NO | — | MUL |
| hobi_id | bigint(20) unsigned | NO | — | MUL |

Foreign key:
- `hobi_id -> hobi.id`; delete RESTRICT, update NO ACTION.
- `siswa_id -> siswa.id`; delete CASCADE, update NO ACTION.

Index:
- `hobi_siswa_siswa_id_hobi_id_unique` (siswa_id, hobi_id); UNIQUE.

## jenis_bantuan

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| nama_bantuan | varchar(255) | NO | — | UNI |
| keterangan | varchar(255) | YES | NULL | — |

Foreign key:
- Tidak ada FK deklaratif. Pointer audit polimorfik dibatasi service.

Index:
- `jenis_bantuan_nama_bantuan_unique` (nama_bantuan); UNIQUE.

## job_batches

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | varchar(255) | NO | — | PRI |
| name | varchar(255) | NO | — | — |
| total_jobs | int(11) | NO | — | — |
| pending_jobs | int(11) | NO | — | — |
| failed_jobs | int(11) | NO | — | — |
| failed_job_ids | longtext | NO | — | — |
| options | mediumtext | YES | NULL | — |
| cancelled_at | int(11) | YES | NULL | — |
| created_at | int(11) | NO | — | — |
| finished_at | int(11) | YES | NULL | — |

Foreign key:
- Tidak ada FK deklaratif. Pointer audit polimorfik dibatasi service.

Index:
- `sqlite_autoindex_job_batches_1` (id); UNIQUE.

## jobs

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| queue | varchar(255) | NO | — | MUL |
| payload | longtext | NO | — | — |
| attempts | tinyint(3) unsigned | NO | — | — |
| reserved_at | int(10) unsigned | YES | NULL | — |
| available_at | int(10) unsigned | NO | — | — |
| created_at | int(10) unsigned | NO | — | — |

Foreign key:
- Tidak ada FK deklaratif. Pointer audit polimorfik dibatasi service.

Index:
- `jobs_queue_index` (queue); non-unique.

## kelas

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| tingkat | varchar(255) | NO | — | — |
| nama_kelas | varchar(255) | NO | — | — |
| tahun_ajaran | varchar(255) | NO | — | — |

Foreign key:
- Tidak ada FK deklaratif. Pointer audit polimorfik dibatasi service.

Index:
- Tidak ada index tambahan dalam introspeksi; PK mengikuti kolom.

## klaster_prodi

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| nama_klaster | varchar(255) | NO | — | — |
| kode_riasec_relevan | varchar(10) | YES | NULL | — |
| kecerdasan_relevan | varchar(255) | YES | NULL | — |
| deskripsi | text | YES | NULL | — |
| bobot_komponen | longtext | YES | NULL | — |
| is_active | tinyint(1) | NO | 1 | — |

Foreign key:
- Tidak ada FK deklaratif. Pointer audit polimorfik dibatasi service.

Index:
- Tidak ada index tambahan dalam introspeksi; PK mengikuti kolom.

## kriteria_kelayakan_beasiswa

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| beasiswa_id | bigint(20) unsigned | NO | — | MUL |
| nama_kriteria | varchar(255) | NO | — | — |
| tipe_kriteria | varchar(255) | NO | — | — |
| operator | varchar(20) | NO | — | — |
| nilai_kriteria | varchar(255) | NO | — | — |
| bobot | decimal(5,2) | YES | NULL | — |

Foreign key:
- `beasiswa_id -> basis_data_beasiswa.id`; delete CASCADE, update NO ACTION.

Index:
- Tidak ada index tambahan dalam introspeksi; PK mengikuti kolom.

## mata_pelajaran

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| nama_mapel | varchar(255) | NO | — | — |
| jenis_mapel | char(1) | NO | — | — |
| kelompok | varchar(255) | YES | NULL | — |
| tersedia_sebagai_pilihan | tinyint(1) | NO | 0 | — |
| deskripsi | text | YES | NULL | — |

Foreign key:
- Tidak ada FK deklaratif. Pointer audit polimorfik dibatasi service.

Index:
- Tidak ada index tambahan dalam introspeksi; PK mengikuti kolom.

## media_sosial

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| siswa_id | bigint(20) unsigned | NO | — | MUL |
| jenis_media | varchar(255) | NO | — | — |
| username | varchar(255) | YES | NULL | — |
| nomor_wa | varchar(255) | YES | NULL | — |

Foreign key:
- `siswa_id -> siswa.id`; delete CASCADE, update NO ACTION.

Index:
- Tidak ada index tambahan dalam introspeksi; PK mengikuti kolom.

## migrations

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | int(10) unsigned | NO | — | PRI auto_increment |
| migration | varchar(255) | NO | — | — |
| batch | int(11) | NO | — | — |

Foreign key:
- Tidak ada FK deklaratif. Pointer audit polimorfik dibatasi service.

Index:
- Tidak ada index tambahan dalam introspeksi; PK mengikuti kolom.

## nilai_rapor

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| siswa_id | bigint(20) unsigned | NO | — | MUL |
| mata_pelajaran_id | bigint(20) unsigned | NO | — | MUL |
| semester | int(11) | NO | — | — |
| nilai_akhir | decimal(5,2) | NO | — | — |

Foreign key:
- `mata_pelajaran_id -> mata_pelajaran.id`; delete RESTRICT, update NO ACTION.
- `siswa_id -> siswa.id`; delete CASCADE, update NO ACTION.

Index:
- `nilai_rapor_unique` (siswa_id, mata_pelajaran_id, semester); UNIQUE.

## orang_tua

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| siswa_id | bigint(20) unsigned | NO | — | MUL |
| jenis_orang_tua | enum('ayah','ibu') | NO | — | — |
| nama | varchar(255) | NO | — | — |
| kondisi | enum('hidup','meninggal','bercerai') | YES | NULL | — |
| pekerjaan | varchar(255) | YES | NULL | — |
| penghasilan | decimal(15,2) | YES | NULL | — |
| alamat | text | YES | NULL | — |
| kontak_wa | varchar(255) | YES | NULL | — |

Foreign key:
- `siswa_id -> siswa.id`; delete CASCADE, update NO ACTION.

Index:
- `orang_tua_siswa_id_jenis_orang_tua_unique` (siswa_id, jenis_orang_tua); UNIQUE.

## password_reset_tokens

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| email | varchar(255) | NO | — | PRI |
| token | varchar(255) | NO | — | — |
| created_at | timestamp | YES | NULL | — |

Foreign key:
- Tidak ada FK deklaratif. Pointer audit polimorfik dibatasi service.

Index:
- `sqlite_autoindex_password_reset_tokens_1` (email); UNIQUE.

## perguruan_tinggi

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| nama_pt | varchar(255) | NO | — | — |
| jenis_pt | varchar(255) | YES | NULL | — |
| kode_pt | varchar(255) | YES | NULL | UNI |
| lokasi | longtext | YES | NULL | — |

Foreign key:
- Tidak ada FK deklaratif. Pointer audit polimorfik dibatasi service.

Index:
- `perguruan_tinggi_kode_pt_unique` (kode_pt); UNIQUE.

## periode_eligibilitas

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| tahun_ajaran | varchar(255) | NO | — | UNI |
| kuota_siswa | int(11) | NO | — | — |
| is_active | tinyint(1) | NO | 0 | — |

Foreign key:
- Tidak ada FK deklaratif. Pointer audit polimorfik dibatasi service.

Index:
- `periode_eligibilitas_tahun_ajaran_unique` (tahun_ajaran); UNIQUE.

## personal_access_tokens

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| tokenable_type | varchar(255) | NO | — | MUL |
| tokenable_id | bigint(20) unsigned | NO | — | — |
| name | text | NO | — | — |
| token | varchar(64) | NO | — | UNI |
| abilities | text | YES | NULL | — |
| last_used_at | timestamp | YES | NULL | — |
| expires_at | timestamp | YES | NULL | MUL |
| created_at | timestamp | YES | NULL | — |
| updated_at | timestamp | YES | NULL | — |

Foreign key:
- Tidak ada FK deklaratif. Pointer audit polimorfik dibatasi service.

Index:
- `personal_access_tokens_expires_at_index` (expires_at); non-unique.
- `personal_access_tokens_token_unique` (token); UNIQUE.
- `personal_access_tokens_tokenable_type_tokenable_id_index` (tokenable_type, tokenable_id); non-unique.

## pilihan_mapel_siswa

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| siswa_id | bigint(20) unsigned | NO | — | MUL |
| mata_pelajaran_id | bigint(20) unsigned | NO | — | MUL |
| tahun_ajaran | varchar(255) | NO | — | — |
| tingkat_kelas | varchar(255) | NO | — | — |
| status | varchar(255) | NO | — | — |
| tanggal_pilihan | date | YES | NULL | — |

Foreign key:
- `mata_pelajaran_id -> mata_pelajaran.id`; delete RESTRICT, update NO ACTION.
- `siswa_id -> siswa.id`; delete CASCADE, update NO ACTION.

Index:
- `pilihan_mapel_unique` (siswa_id, mata_pelajaran_id, tahun_ajaran); UNIQUE.

## prestasi_murid

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| siswa_id | bigint(20) unsigned | NO | — | MUL |
| jenis_prestasi | varchar(255) | NO | — | — |
| nama_kompetisi | varchar(255) | NO | — | — |
| bidang | varchar(255) | YES | NULL | — |
| tingkat | varchar(255) | YES | NULL | — |
| peringkat | varchar(255) | YES | NULL | — |
| bobot_persentase | decimal(5,2) | YES | NULL | — |
| status_verifikasi | varchar(255) | NO | 'pending' | — |
| penyelenggara | varchar(255) | YES | NULL | — |
| tahun | int(11) | YES | NULL | — |
| file_bukti_url | varchar(500) | YES | NULL | — |
| attachment_id | bigint(20) unsigned | YES | NULL | MUL |

Foreign key:
- `attachment_id -> private_attachments.id`; delete SET NULL, update NO ACTION.
- `siswa_id -> siswa.id`; delete CASCADE, update NO ACTION.

Index:
- Tidak ada index tambahan dalam introspeksi; PK mengikuti kolom.

## private_attachments

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| siswa_id | bigint(20) unsigned | NO | — | MUL |
| path | varchar(500) | NO | — | — |
| mime | varchar(100) | NO | — | — |
| original_name | varchar(255) | NO | — | — |
| size | int(10) unsigned | NO | — | — |
| uploaded_by | bigint(20) unsigned | NO | — | MUL |
| created_at | timestamp | YES | NULL | — |
| updated_at | timestamp | YES | NULL | — |

Foreign key:
- `uploaded_by -> users.id`; delete RESTRICT, update NO ACTION.
- `siswa_id -> siswa.id`; delete CASCADE, update NO ACTION.

Index:
- Tidak ada index tambahan dalam introspeksi; PK mengikuti kolom.

## program_eligibilitas

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| nama | varchar(255) | NO | — | — |
| deskripsi | text | YES | NULL | — |
| bobot_prestasi | longtext | YES | NULL | — |
| metode_prestasi | varchar(30) | YES | NULL | — |
| bantuan_ids | longtext | YES | NULL | — |
| mode_bantuan | varchar(20) | YES | NULL | — |
| kriteria | longtext | NO | — | — |
| version | int(10) unsigned | NO | 1 | — |
| is_active | tinyint(1) | NO | 1 | — |
| created_at | timestamp | YES | NULL | — |
| updated_at | timestamp | YES | NULL | — |

Foreign key:
- Tidak ada FK deklaratif. Pointer audit polimorfik dibatasi service.

Index:
- Tidak ada index tambahan dalam introspeksi; PK mengikuti kolom.

## published_cohort_summaries

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| beasiswa_id | bigint(20) unsigned | NO | — | MUL |
| angkatan | smallint(5) unsigned | NO | — | — |
| summary | longtext | NO | — | — |
| member_siswa_ids | longtext | NO | — | — |
| is_published | tinyint(1) | NO | 0 | — |
| created_by | bigint(20) unsigned | NO | — | MUL |
| created_at | timestamp | YES | NULL | — |
| updated_at | timestamp | YES | NULL | — |

Foreign key:
- `created_by -> users.id`; delete RESTRICT, update NO ACTION.
- `beasiswa_id -> basis_data_beasiswa.id`; delete RESTRICT, update NO ACTION.

Index:
- Tidak ada index tambahan dalam introspeksi; PK mengikuti kolom.

## rekap_presensi

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| siswa_id | bigint(20) unsigned | NO | — | MUL |
| semester | int(11) | NO | — | — |
| jumlah_hadir | int(11) | NO | 0 | — |
| jumlah_sakit | int(11) | NO | 0 | — |
| jumlah_izin | int(11) | NO | 0 | — |
| jumlah_alpa | int(11) | NO | 0 | — |
| persentase_kehadiran | decimal(5,2) | NO | 0.00 | — |

Foreign key:
- `siswa_id -> siswa.id`; delete CASCADE, update NO ACTION.

Index:
- `rekap_presensi_siswa_id_semester_unique` (siswa_id, semester); UNIQUE.

## rekomendasi_beasiswa

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| siswa_id | bigint(20) unsigned | NO | — | MUL |
| tanggal_rekomendasi | date | NO | — | — |
| status_review | enum('pending','disetujui','ditolak') | NO | 'pending' | — |
| catatan_admin | text | YES | NULL | — |
| ai_model | varchar(255) | YES | NULL | — |
| prompt_version | varchar(255) | YES | NULL | — |
| source_fingerprint | varchar(64) | YES | NULL | — |
| reviewed_by | bigint(20) unsigned | YES | NULL | — |
| reviewed_at | timestamp | YES | NULL | — |
| target_jenjang | varchar(20) | YES | NULL | — |

Foreign key:
- `siswa_id -> siswa.id`; delete CASCADE, update NO ACTION.

Index:
- Tidak ada index tambahan dalam introspeksi; PK mengikuti kolom.

## rekomendasi_beasiswa_detail

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| rekomendasi_beasiswa_id | bigint(20) unsigned | NO | — | MUL |
| beasiswa_id | bigint(20) unsigned | NO | — | MUL |
| skor_kecocokan | decimal(8,4) | NO | — | — |
| alasan_rekomendasi | text | YES | NULL | — |
| is_recommended | tinyint(1) | NO | 0 | — |
| evaluasi_kriteria | longtext | YES | NULL | — |
| beasiswa_snapshot | longtext | YES | NULL | — |
| persiapan | longtext | YES | NULL | — |

Foreign key:
- `beasiswa_id -> basis_data_beasiswa.id`; delete RESTRICT, update NO ACTION.
- `rekomendasi_beasiswa_id -> rekomendasi_beasiswa.id`; delete CASCADE, update NO ACTION.

Index:
- Tidak ada index tambahan dalam introspeksi; PK mengikuti kolom.

## rekomendasi_mapel

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| siswa_id | bigint(20) unsigned | NO | — | MUL |
| mata_pelajaran_id | bigint(20) unsigned | NO | — | MUL |
| skor_rekomendasi | decimal(8,2) | NO | — | — |
| tingkat_rekomendasi | varchar(255) | YES | NULL | — |
| alasan_rekomendasi | text | YES | NULL | — |
| tanggal_rekomendasi | date | NO | — | — |
| tahun_ajaran | varchar(255) | NO | — | — |

Foreign key:
- `mata_pelajaran_id -> mata_pelajaran.id`; delete RESTRICT, update NO ACTION.
- `siswa_id -> siswa.id`; delete CASCADE, update NO ACTION.

Index:
- Tidak ada index tambahan dalam introspeksi; PK mengikuti kolom.

## rekomendasi_prodi

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| siswa_id | bigint(20) unsigned | NO | — | MUL |
| klaster_id | bigint(20) unsigned | NO | — | MUL |
| study_tracer_id | bigint(20) unsigned | YES | NULL | MUL |
| semester | int(11) | NO | — | — |
| nilai_rata_siswa | decimal(5,2) | YES | NULL | — |
| persentase_kesesuaian | decimal(5,2) | YES | NULL | — |
| alasan_rekomendasi | text | YES | NULL | — |
| tanggal_rekomendasi | datetime | NO | — | — |
| batch_id | char(36) | YES | NULL | MUL |
| status_review | varchar(20) | NO | 'pending' | — |
| source_fingerprint | varchar(64) | YES | NULL | — |
| detail_penilaian | longtext | YES | NULL | — |
| criteria_snapshot | longtext | YES | NULL | — |
| catatan_admin | text | YES | NULL | — |
| reviewed_by | bigint(20) unsigned | YES | NULL | MUL |
| reviewed_at | timestamp | YES | NULL | — |

Foreign key:
- `reviewed_by -> users.id`; delete SET NULL, update NO ACTION.
- `siswa_id -> siswa.id`; delete CASCADE, update NO ACTION.
- `klaster_id -> klaster_prodi.id`; delete RESTRICT, update NO ACTION.
- `study_tracer_id -> study_tracer.id`; delete SET NULL, update NO ACTION.

Index:
- `rekomendasi_prodi_batch_id_index` (batch_id); non-unique.

## riwayat_kelas

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| siswa_id | bigint(20) unsigned | NO | — | MUL |
| kelas_id | bigint(20) unsigned | NO | — | MUL |
| tanggal_mulai | date | NO | — | — |
| tanggal_selesai | date | YES | NULL | — |
| keterangan | varchar(255) | YES | NULL | — |

Foreign key:
- `kelas_id -> kelas.id`; delete RESTRICT, update NO ACTION.
- `siswa_id -> siswa.id`; delete CASCADE, update NO ACTION.

Index:
- Tidak ada index tambahan dalam introspeksi; PK mengikuti kolom.

## riwayat_organisasi

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| siswa_id | bigint(20) unsigned | NO | — | MUL |
| jenis_organisasi | varchar(255) | NO | — | — |
| nama_organisasi | varchar(255) | NO | — | — |
| jabatan | varchar(255) | YES | NULL | — |
| bobot_jabatan | decimal(5,2) | YES | NULL | — |
| periode_jabatan | varchar(255) | YES | NULL | — |

Foreign key:
- `siswa_id -> siswa.id`; delete CASCADE, update NO ACTION.

Index:
- Tidak ada index tambahan dalam introspeksi; PK mengikuti kolom.

## saudara_tiri

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| siswa_id | bigint(20) unsigned | NO | — | MUL |
| nama | varchar(255) | NO | — | — |
| jenis_kelamin | varchar(255) | YES | NULL | — |
| hubungan | varchar(255) | YES | NULL | — |
| usia | int(11) | YES | NULL | — |
| keterangan | varchar(255) | YES | NULL | — |

Foreign key:
- `siswa_id -> siswa.id`; delete CASCADE, update NO ACTION.

Index:
- Tidak ada index tambahan dalam introspeksi; PK mengikuti kolom.

## school_import_batches

**Tambahan baru**

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | varchar | NO | — | PK |
| created_by | INTEGER | NO | — | — |
| source | varchar | NO | — | — |
| mode | varchar | NO | — | — |
| status | varchar | NO | — | — |
| fingerprint | varchar | NO | — | — |
| request_fingerprint | varchar | NO | — | — |
| commit_digest | varchar | NO | — | — |
| plan | TEXT | YES | — | — |
| summary | TEXT | NO | — | — |
| receipt | TEXT | YES | — | — |
| expires_at | datetime | NO | — | — |
| completed_at | datetime | YES | — | — |
| created_at | datetime | YES | — | — |
| updated_at | datetime | YES | — | — |

Foreign key:
- `created_by -> users.id`; delete RESTRICT, update NO ACTION.

Index:
- `school_import_batches_request_fingerprint_index` (request_fingerprint); non-unique.
- `school_import_batches_fingerprint_unique` (fingerprint); UNIQUE.
- `school_import_batches_status_index` (status); non-unique.
- `sqlite_autoindex_school_import_batches_1` (id); UNIQUE.

## school_import_links

**Tambahan baru**

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | INTEGER | NO | — | PK |
| source | varchar | NO | — | — |
| module | varchar | NO | — | — |
| external_key | varchar | NO | — | — |
| record_table | varchar | NO | — | — |
| record_id | INTEGER | NO | — | — |
| payload_hash | varchar | NO | — | — |
| last_batch_id | varchar | NO | — | — |
| created_at | datetime | YES | — | — |
| updated_at | datetime | YES | — | — |

Foreign key:
- `last_batch_id -> school_import_batches.id`; delete RESTRICT, update NO ACTION.

Index:
- `school_import_source_key` (source, module, external_key); UNIQUE.

## school_staff_grants

**Tambahan baru**

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| user_id | INTEGER | NO | — | PK |
| granted_email | varchar | NO | — | — |
| granted_by | INTEGER | YES | — | — |
| granted_at | datetime | NO | — | — |
| revoked_at | datetime | YES | — | — |

Foreign key:
- `granted_by -> users.id`; delete SET NULL, update NO ACTION.
- `user_id -> users.id`; delete CASCADE, update NO ACTION.

Index:
- Tidak ada index tambahan dalam introspeksi; PK mengikuti kolom.

## sessions

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | varchar(255) | NO | — | PRI |
| user_id | bigint(20) unsigned | YES | NULL | MUL |
| ip_address | varchar(45) | YES | NULL | — |
| user_agent | text | YES | NULL | — |
| payload | longtext | NO | — | — |
| last_activity | int(11) | NO | — | MUL |

Foreign key:
- Tidak ada FK deklaratif. Pointer audit polimorfik dibatasi service.

Index:
- `sessions_last_activity_index` (last_activity); non-unique.
- `sessions_user_id_index` (user_id); non-unique.
- `sqlite_autoindex_sessions_1` (id); UNIQUE.

## siswa

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| user_id | bigint(20) unsigned | YES | NULL | UNI |
| nisn | varchar(255) | YES | NULL | UNI |
| nis | varchar(255) | YES | NULL | — |
| nama_lengkap | varchar(255) | NO | — | — |
| nama_panggilan | varchar(255) | YES | NULL | — |
| jenis_kelamin | varchar(255) | YES | NULL | — |
| tempat_lahir | varchar(255) | YES | NULL | — |
| tanggal_lahir | date | YES | NULL | — |
| agama | varchar(255) | YES | NULL | — |
| golongan_darah | varchar(255) | YES | NULL | — |
| alamat_rumah | text | YES | NULL | — |
| tinggal_bersama | varchar(255) | YES | NULL | — |
| anak_ke | int(11) | YES | NULL | — |
| warna_favorit | varchar(255) | YES | NULL | — |
| makanan_favorit | varchar(255) | YES | NULL | — |
| bahasa_keseharian | varchar(255) | YES | NULL | — |
| permainan_online | varchar(255) | YES | NULL | — |
| permainan_offline | varchar(255) | YES | NULL | — |
| nilai_tka | decimal(5,2) | YES | NULL | — |
| catatan | text | YES | NULL | — |
| created_at | timestamp | YES | NULL | — |
| updated_at | timestamp | YES | NULL | — |
| registration_email | varchar(255) | YES | NULL | MUL |
| tanggal_masuk_sekolah | date | YES | NULL | — |
| tanggal_lulus | date | YES | NULL | — |
| tahun_lulus | smallint(5) unsigned | YES | NULL | — |
| bantuan_dikonfirmasi | tinyint(1) | NO | 0 | — |
| prestasi_dikonfirmasi | tinyint(1) | NO | 0 | — |
| organisasi_dikonfirmasi | tinyint(1) | NO | 0 | — |
| karakter_dikonfirmasi | tinyint(1) | NO | 0 | — |
| izinkan_analitik | tinyint(1) | NO | 0 | — |
| referensi_izin_analitik | varchar(500) | YES | NULL | — |
| izin_analitik_at | timestamp | YES | NULL | — |

Foreign key:
- `user_id -> users.id`; delete CASCADE, update NO ACTION.

Index:
- `siswa_registration_email_index` (registration_email); non-unique.
- `siswa_user_id_unique` (user_id); UNIQUE.
- `siswa_nisn_unique` (nisn); UNIQUE.

## student_profile_submissions

**Tambahan baru**

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | varchar | NO | — | PK |
| siswa_id | INTEGER | NO | — | — |
| submitted_by | INTEGER | NO | — | — |
| payload | TEXT | NO | — | — |
| source_fingerprint | varchar | NO | — | — |
| status | varchar | NO | 'pending' | — |
| reviewed_by | INTEGER | YES | — | — |
| reviewed_at | datetime | YES | — | — |
| created_at | datetime | YES | — | — |
| updated_at | datetime | YES | — | — |

Foreign key:
- `reviewed_by -> users.id`; delete SET NULL, update NO ACTION.
- `submitted_by -> users.id`; delete RESTRICT, update NO ACTION.
- `siswa_id -> siswa.id`; delete CASCADE, update NO ACTION.

Index:
- `student_profile_submissions_siswa_id_status_index` (siswa_id, status); non-unique.
- `sqlite_autoindex_student_profile_submissions_1` (id); UNIQUE.

## study_tracer

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| siswa_id | bigint(20) unsigned | NO | — | MUL |
| perguruan_tinggi_id | bigint(20) unsigned | NO | — | MUL |
| buku_alumni_id | bigint(20) unsigned | YES | NULL | MUL |
| strata_pendidikan | varchar(255) | NO | — | — |
| program_studi | varchar(255) | NO | — | — |
| jalur_masuk | varchar(255) | NO | — | — |
| sub_jalur_mandiri | varchar(255) | YES | NULL | — |
| pilihan_ke | int(11) | YES | NULL | — |
| status_penerimaan | enum('menunggu','diterima','tidak_diterima') | NO | 'menunggu' | — |
| nilai_rapor_pendaftaran | double | YES | NULL | — |
| skor_utbk | double | YES | NULL | — |
| kontak_wa_terbaru | varchar(255) | YES | NULL | — |
| is_pelamar_kipk | tinyint(1) | NO | 0 | — |
| izinkan_tampil_di_buku | tinyint(1) | NO | 0 | — |
| tanggal_pengisian | date | YES | NULL | — |
| tahun_masuk_pt | smallint(5) unsigned | YES | NULL | — |
| tanggal_masuk_pt | date | YES | NULL | — |
| consent_reference | varchar(500) | YES | NULL | — |
| consent_at | timestamp | YES | NULL | — |
| consent_by | bigint(20) unsigned | YES | NULL | MUL |

Foreign key:
- `consent_by -> users.id`; delete SET NULL, update NO ACTION.
- `siswa_id -> siswa.id`; delete CASCADE, update NO ACTION.
- `perguruan_tinggi_id -> perguruan_tinggi.id`; delete RESTRICT, update NO ACTION.
- `buku_alumni_id -> buku_alumni.id`; delete SET NULL, update NO ACTION.

Index:
- `tracer_university_path` (perguruan_tinggi_id, jalur_masuk); non-unique.

## study_tracer_prestasi

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| study_tracer_id | bigint(20) unsigned | NO | — | MUL |
| prestasi_murid_id | bigint(20) unsigned | NO | — | MUL |

Foreign key:
- `prestasi_murid_id -> prestasi_murid.id`; delete CASCADE, update NO ACTION.
- `study_tracer_id -> study_tracer.id`; delete CASCADE, update NO ACTION.

Index:
- `study_tracer_prestasi_unique` (study_tracer_id, prestasi_murid_id); UNIQUE.

## users

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| username | varchar(255) | NO | — | UNI |
| password | varchar(255) | YES | NULL | — |
| role | enum('administrator','guru','siswa') | NO | — | — |
| created_at | timestamp | YES | NULL | — |
| updated_at | timestamp | YES | NULL | — |
| email | varchar(255) | YES | NULL | UNI |
| email_verified_at | timestamp | YES | NULL | — |
| remember_token | varchar(100) | YES | NULL | — |
| full_name | varchar(255) | YES | NULL | — |
| google_id | varchar(255) | YES | NULL | UNI |
| photo_path | varchar(500) | YES | NULL | — |

Foreign key:
- Tidak ada FK deklaratif. Pointer audit polimorfik dibatasi service.

Index:
- `users_google_id_unique` (google_id); UNIQUE.
- `users_username_unique` (username); UNIQUE.
- `users_email_unique` (email); UNIQUE.

## wali

Tabel existing dipertahankan.

| Kolom | Tipe | Nullable | Default | Kunci / catatan |
|---|---|---|---|---|
| id | bigint(20) unsigned | NO | — | PRI auto_increment |
| siswa_id | bigint(20) unsigned | NO | — | UNI |
| nama | varchar(255) | NO | — | — |
| hubungan | varchar(255) | YES | NULL | — |
| pekerjaan | varchar(255) | YES | NULL | — |
| penghasilan | decimal(15,2) | YES | NULL | — |
| alamat | text | YES | NULL | — |
| kontak_wa | varchar(255) | YES | NULL | — |

Foreign key:
- `siswa_id -> siswa.id`; delete CASCADE, update NO ACTION.

Index:
- `wali_siswa_id_unique` (siswa_id); UNIQUE.

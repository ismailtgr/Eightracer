# Arsitektur backend Eightracer B4

Revisi 2 November 2026. Implementasi mengikuti Laravel/MySQL proyek yang sudah ada. Lingkup ini backend; UI tim FE tidak diubah. Status pemasangan sebenarnya dicatat pada `installation-status.json` dalam paket hasil.

## Pemetaan seluruh entitas permintaan

| Entitas konseptual | Tabel proyek | Hubungan dan makna |
|---|---|---|
| Users/Auth/RBAC | `users`, `administrator`, `guru`, `personal_access_tokens`, `auth_challenges`, `sessions` | Role database tetap administrator/guru/siswa. NISN login mengarah ke akun siswa yang sudah terhubung, bukan membuat password dari NISN. |
| Students/Profiles | `siswa`, `riwayat_kelas`, `kelas`, `orang_tua`, `wali`, `hobi_siswa`, `cita_cita_siswa` | Satu siswa dapat belum memiliki akun. NISN asli unik; data keluarga dipisah dari akun. |
| AcademicRecords | `nilai_rapor`, `mata_pelajaran` | Unik siswa + mapel + semester; impor SMA dibatasi semester 1–5, nilai 0–100. |
| Assessments | `asesmen_karakteristik`, `asesmen_riasec`, `asesmen_multiple_intelligence` | Siswa 1:N versi asesmen; satu asesmen memiliki skor RIASEC dan skor MI. Tanggal nyata wajib, koreksi menunjuk versi lama. |
| Achievements | `prestasi_murid`, `private_attachments` | Sumber sekolah dipertahankan. Status pending adalah antrean review aplikasi. Bidang terverifikasi dapat menjadi fitur minat ML. |
| AlumniRecords | `study_tracer`, `perguruan_tinggi`, `study_tracer_prestasi` | Siswa 1:N penerimaan; universitas, prodi, jalur, status, tahun masuk dicatat per penerimaan. |
| FinancialData | `alumni_progress`, `alumni_progress_beasiswa` | Progres berperiode/revisi berisi UKT, IPI, semester, IPK, KIP-K; detail 1:N beasiswa berisi program, status dan nominal. Tidak dibuat tabel FinancialData paralel. |
| GovernmentAid | `jenis_bantuan`, `bantuan_siswa` | Jenis PIP/PKH/KIS/KIP yang benar-benar terdaftar menjadi referensi; nomor/periode/status penerima dipisah. KIP-K kuliah juga memiliki flag pada progres, jangan menyamakan status pelamar dengan penerima. |
| Scholarship catalog/criteria | `basis_data_beasiswa`, `kriteria_kelayakan_beasiswa`, `program_eligibilitas`, `hasil_program_eligibilitas` | Beasiswa memiliki deskripsi/sumber/periode/jenjang dan syarat terstruktur. Batas penghasilan/nilai berasal dari kriteria yang disimpan, bukan tebakan LLM. |
| Recommendation/audit | `rekomendasi_beasiswa`, `rekomendasi_beasiswa_detail`, `published_cohort_summaries` | Draft, checklist, snapshot, fingerprint, metadata ML dan review; hanya hasil disetujui untuk siswa. |

Relasi finansial: `siswa -> study_tracer -> alumni_progress -> alumni_progress_beasiswa -> basis_data_beasiswa`. Setiap `alumni_progress` juga menunjuk siswa. Service memvalidasi bahwa penerimaan adalah milik siswa tersebut. Koreksi progres membuat revisi; publikasi harus ditinjau ulang. `null` pada UKT/IPK/penghasilan berarti belum diketahui, bukan nol.

## Empat tabel alur kerja baru

| Tabel | Kunci/relasi | Tujuan |
|---|---|---|
| `school_staff_grants` | PK/FK user_id; granted_by -> users | Hak Guru BK eksplisit, email terverifikasi sesuai allowlist, waktu grant/revoke. |
| `school_import_batches` | UUID PK; created_by -> users; fingerprint unik | Rencana terenkripsi, ringkasan validasi, digest, status, kedaluwarsa 24 jam, receipt. request_fingerprint memungkinkan preview ulang batch gagal/kedaluwarsa. |
| `school_import_links` | Unik source/module/external_key; last_batch_id -> batch | Pemetaan ID respons sumber ke record domain, pencegahan pemindahan respons antar murid/periode. record_table/id adalah pointer audit yang divalidasi allowlist service, bukan FK polimorfik database. |
| `student_profile_submissions` | UUID PK; siswa_id, submitted_by, reviewed_by | Usulan profil awal terenkripsi, pending/approved/rejected, fingerprint untuk mencegah menimpa master yang sudah berubah. |

Skema kandidat hasil migrasi terisolasi: **62 tabel, 515 kolom**. Baseline MySQL yang dibaca: **58 tabel, 475 kolom**. Daftar kolom lengkap ada di `b4-schema-lengkap.md`; DDL MySQL lengkap di `b4-schema-reference.sql`; sumber otoritatif perubahan adalah migration `2026_11_02_000004_complete_b4_workflows.php`. DDL referensi hanya untuk basis data kosong. Jangan menjalankannya pada database sekolah yang sudah terisi.

Index baru: progres `(siswa_id,is_current,tanggal_update,id)`, detail beasiswa `(beasiswa_id,status_beasiswa,alumni_progress_id)`, tracer `(perguruan_tinggi_id,jalur_masuk)`. Index sebelumnya tetap dipakai. Dashboard memilih progres terbaru dahulu, kemudian menerapkan filter biaya/status agar catatan lama yang murah tidak muncul sebagai keadaan terbaru.

## Alur AI/RAG beasiswa

1. Middleware memeriksa token, umur sesi dan peran. Endpoint siswa mengambil profil dari pemilik token; siswa_id/history dari browser ditolak.
2. Validasi pesan (3–2.000 karakter), ID beasiswa opsional, jenjang SMA/Kuliah. Jika nama ambigu, sistem meminta pilihan; katalog kosong menghasilkan keterangan belum tersedia.
3. Ambil rapor/profil milik siswa, progres kuliah jika relevan dan syarat resmi program dari database. Evaluator menghitung checklist terpenuhi/belum terpenuhi/belum diketahui. Jenjang yang belum sesuai ditandai sebagai persiapan, bukan diluluskan otomatis.
4. Ambil catatan terbaru per alumni untuk program tersebut, hanya yang mengizinkan analitik dan dipublikasikan. Pisahkan hasil diterima/ditolak; proses bukan hasil terminal. Jangan menganggap penerimaan PT sebagai penerimaan beasiswa.
5. Proporsi hanya ditampilkan bila masing-masing kelompok diterima dan ditolak memiliki setidaknya 5 alumni. Rentang rapor memerlukan setidaknya 5 penerima dengan rapor semester 1–5, dibulatkan ke bucket 5 poin. Jalur masuk disembunyikan bila ada kelompok kecil. Jika tidak cukup, tampilkan belum cukup data; tidak menebak peluang lolos.
6. Susun fakta ber-ID: hasil kriteria, rapor pribadi yang diketahui, bukti agregat dan langkah persiapan. Nama/NISN/email milik siswa dan pola kontak/URL pada pesan disamarkan; profil mentah, alamat, penghasilan mentah dan lampiran tidak dikirim ke provider. Pesan bebas tetap perlu dihindarkan dari data pribadi pihak lain; penyamaran bukan detektor semua PII.
7. Gemini menerima konteks minimal dengan `store=false` dan diminta memilih ID fakta/tindakan. Respons disaring terhadap ID yang diizinkan. Backend merender fakta sendiri; teks/angka bebas dari model tidak diteruskan. Fakta kelayakan dan ketidakpastian selalu disertakan. Bentuk request structured output mengikuti [referensi Interactions API Google](https://ai.google.dev/api/interactions-api).
8. Provider gagal/key belum ada/format salah: jawaban deterministik dari fakta yang sama, `llm_used=false`. Error provider, key dan informasi internal server tidak dikirim ke FE.
9. Kembalikan answer, checklist, historical_evidence dan sources. Endpoint chat tidak menyimpan percakapan, tidak memakai history dari klien dan tidak membuat draft rekomendasi. Ini asisten terarah untuk beasiswa dalam katalog, belum chatbot percakapan umum lintas topik.

Perhitungan resmi, ML dan LLM berbeda: evaluator menegakkan syarat; ML TF-IDF/cosine mengurutkan alternatif eligible dari isi katalog dan minat terstruktur; Gemini membantu penjelasan/pemilihan tindakan. Skor kemiripan bukan probabilitas diterima. Draft rekomendasi tetap perlu review admin/BK sebelum tampil kepada siswa. Detail algoritma/evaluasi ada di `ml-beasiswa.md`.

## Pilihan stack

| Kebutuhan | Yang diterapkan sekarang | Alternatif Node/Python jika dipisah menjadi service |
|---|---|---|
| REST API/database | Laravel 12, PHP sesuai composer (^8.4.1), MySQL, Sanctum | NestJS + Prisma untuk Node; FastAPI + validasi model untuk Python. Tidak perlu menulis ulang aplikasi saat ini. |
| Bulk CSV/XLSX | Pembaca OpenXML/CSV terbatas + validasi service bersama + queue + transaksi | ExcelJS streaming reader untuk Node; openpyxl read_only untuk Python. Parser tetap harus dibatasi, dipindai, dan dipetakan ke identitas yang benar. |
| PDF | Python ReportLab dipanggil backend lewat stdin, tabel agregat B&W, tanpa browser | Playwright page.pdf untuk template HTML/CSS pada service Node terisolasi; ReportLab cocok untuk laporan tabel Python. |
| Rekomendasi | TF-IDF/cosine lokal di PHP + Gemini server-side | scikit-learn untuk eksperimen offline, evaluasi NDCG/Precision@K; serving Python baru diperlukan bila beban/model berkembang. |

Dokumentasi primer: [NestJS](https://docs.nestjs.com/), [Prisma](https://www.prisma.io/docs/orm), [FastAPI](https://fastapi.tiangolo.com/), [ExcelJS](https://github.com/exceljs/exceljs), [openpyxl read-only](https://openpyxl.readthedocs.io/en/stable/optimized.html), [Playwright PDF](https://playwright.dev/docs/api/class-page#page-pdf), [ReportLab](https://docs.reportlab.com/reportlab/userguide/ch1_intro/). Alternatif ini rekomendasi arsitektur; bukan dependensi tambahan yang sudah dipasang dalam proyek.

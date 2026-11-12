# Kontrak API backend B4 untuk FE

Kontrak setelah merge main: baca [INTEGRASI-MAIN-B3-B4.md](INTEGRASI-MAIN-B3-B4.md). Direktori B4 memakai `/api/b4/alumni`; `/api/alumni` mempertahankan kontrak B3.
Seluruh URL di bawah diawali `/api`. Gunakan base URL backend privat/HTTPS yang disepakati. Jangan memberi FE isi .env, key Gemini, akses phpMyAdmin atau password database. Autentikasi memakai `Authorization: Bearer <token>` dan `Accept: application/json`; untuk unggahan biarkan browser membuat boundary multipart.

`staff` berarti administrator atau guru dengan grant BK aktif, email terverifikasi dan allowlist yang sesuai. Administrator tetap satu-satunya pihak yang dapat memberi/mencabut grant BK. Role guru tanpa grant ditolak. Semua ID di contoh adalah ilustrasi dan harus diganti ID referensi aktual.

## Autentikasi dan profil

| Method/URL | Hak | Input | Respons sukses |
|---|---|---|---|
| POST /login | Publik, rate limit | `{nisn:"0012345678",password:"..."}` ATAU `{email:"...",password:"..."}`; jangan kirim role atau kedua identifier sekaligus | 200 `{message,user:{id,email,role,school_staff,school_role,email_verified,...},token,expires_at}` |
| POST /register | Publik | Tidak menerima pendaftaran mandiri | 410; akun disiapkan Admin/sekolah |
| POST /register/verify | Publik | Pendaftaran mandiri dinonaktifkan | 410 |
| POST /forgot-password | Publik | email | 200 challenge_id,expires_in,message generik |
| POST /forgot-password/verify | Publik | challenge_id,otp | 200 challenge_id baru,reset_token,expires_in |
| POST /reset-password | Publik | challenge_id,reset_token,password,password_confirmation | 200 message; token/sesi lama dicabut |
| GET /me | Login | Tanpa body | Proyeksi akun aktif; gunakan sebagai sumber peran FE |
| POST /logout | Login | Tanpa body | Pesan logout, token aktif dicabut |
| PUT /school-staff/{user}/bk-access | Administrator | `{enabled:true}` atau false | 200 data grant/status; token target dicabut, login ulang |
| GET /profil-siswa/saya | Siswa | Tanpa body/ID siswa | 200 `{data:{id,nisn,nama_lengkap,jenis_kelamin,nama_panggilan,alamat_rumah,bahasa_keseharian,hobi,cita_cita,submissions}}` |
| POST /profil-siswa/saya/submission | Siswa | Sebagian dari nama_panggilan,alamat_rumah,bahasa_keseharian,hobi:[ID],cita_cita:[ID] | 201 `{data:{id,status:"pending",...},message}`; belum menimpa master |
| GET /profile-submissions | Staff | Pagination page | Paginator antrean pending, tanpa payload privat otomatis |
| GET /profile-submissions/{UUID} | Staff | Tanpa body | 200 `{data,proposed_changes}` untuk review |
| PATCH /profile-submissions/{UUID}/review | Staff | `{status:"approved"}` atau rejected | 200 `{data}`; 409 bila sudah direview/master berubah |

Password baru mengikuti guard existing: minimal 12 karakter, huruf besar/kecil, angka, simbol, maksimum 72 byte. Registrasi tidak menerima role admin/BK. Google login/tautkan akun dan OTP perubahan profil existing tetap tersedia; kontraknya ada di dokumentasi PRD sebelumnya dan inventaris route lengkap `b4-routes-tested.json`.

Siswa tidak dapat mengubah NISN, role, nilai, ekonomi, persetujuan publikasi atau ID pemilik melalui submission. Guru BK harus dikonfigurasi belakangan karena email sekolah belum tersedia.

## Bulk import: preview -> commit -> pantau

| Method/URL | Input | Respons |
|---|---|---|
| GET /imports/templates/{module} | module: students/grades/assessments/alumni/financial/aid/economy | 200 unduhan CSV UTF-8 BOM dengan header canonical + external_id |
| POST /imports | Multipart file CSV/XLSX <=5 MiB, source stabil, mode insert_only/upsert; module untuk satu tab atau JSON sheets+ignore_sheets untuk beberapa tab; number_format canonical/id; delimiter comma/semicolon/tab | 201 `{data:Batch}`; periksa status dan seluruh ringkasan, jangan otomatis commit |
| POST /imports/{UUID}/commit | JSON `{commit_digest:"<64 hex dari preview>"}` | 202 `{data:Batch}` queued; perlu worker. Bisa completed bila batch sudah selesai. |
| GET /imports/{UUID} | Hanya pembuat batch yang bersangkutan | 200 `{data:Batch}`; polling sampai completed/failed/expired |

Semua endpoint impor untuk staff. Bentuk Batch:

```json
{
  "id":"UUID",
  "status":"validated",
  "source":"school-forms-2026",
  "mode":"insert_only",
  "commit_digest":"64-karakter-digest",
  "summary":{
    "total":2,"valid":2,"invalid":0,"errors":[],"errors_truncated":false,
    "sheets":[{"sheet":"Nilai","module":"grades","jumlah_baris":2}],"ignored_sheets":[]
  },
  "receipt":null,
  "expires_at":"ISO-8601",
  "sample":[{"sheet":"Nilai","row":2,"module":"grades","data":{"semester":1,"mata_pelajaran_id":1,"nilai_akhir":88}}]
}
```

Contoh bentuk saja; digest nyata harus disalin persis dari preview. `sample` maksimal 3 baris hanya untuk pembuat batch dan bisa memuat data pribadi; jangan log/share. `summary.errors` memuat sheet,row,errors per field, dibatasi 100, tanpa menggemakan isi sel. Batch invalid tidak boleh commit. Receipt sukses: `{processed:2,per_module:{grades:2}}`; gagal: processed=0 dan code generik. Seluruh domain dibatalkan jika satu baris gagal saat commit. Rencana dibersihkan setelah sukses/kedaluwarsa. Unggah ulang file+opsi yang sama memakai batch valid/completed; batch expired/failed perlu preview baru. Detail pemetaan dan batas ada di `b4-impor.md`.

## Data master dan finansial existing

| Method/URL | Input penting | Output |
|---|---|---|
| GET /siswa | Pagination/filter existing | Paginator profil untuk staff |
| GET /siswa/{id}/data-pendukung | ID milik target | Data rapor/pendukung profil untuk staff |
| PUT /siswa/{id}/nilai-rapor | `{semester:1,nilai:[{mata_pelajaran_id:1,nilai_akhir:88}]}` | `{data:[record nilai semester]}` |
| POST /siswa/{id}/asesmen | tanggal_asesmen ISO + iq/gaya_belajar/tipe_kepribadian/riasec/multiple_intelligence yang benar-benar ada; koreksi_dari_id bila koreksi | 201 data versi asesmen |
| POST /study-tracer | siswa_id,perguruan_tinggi_id,program_studi,strata_pendidikan,jalur_masuk,status_penerimaan,tahun_masuk_pt; nilai_rapor_pendaftaran untuk SNBP, skor_utbk untuk SNBT; subjalur khusus Mandiri | 201 data penerimaan |
| GET /study-tracer | search,angkatan,jenis_pt,perguruan_tinggi_id,program_studi,jalur_masuk,penerima_kipk,beasiswa_id,per_page | Paginator penerimaan; filter beasiswa existing mencari status diterima. Gunakan dashboard baru untuk rentang biaya/status progres terakhir. |
| POST /alumni/{id}/progress | study_tracer_id,periode,semester,tahun_lulus,status_pendidikan,tanggal_update; ipk_terkini,nominal_ukt,nominal_ipi,penerima_kipk,beasiswa:[{beasiswa_id,status_beasiswa,nominal_beasiswa}] opsional | 201 data progres |
| PATCH /alumni-progress/{id} | Payload progres koreksi | Data revisi baru; versi terdahulu dipertahankan, publikasi dicabut |
| GET /alumni/{id}/timeline | ID alumni | Riwayat progres yang diizinkan untuk staff |
| PATCH /alumni-progress/{id}/publikasi | Kontrak persetujuan publikasi existing | Status publikasi setelah validasi izin |

Hak seluruh operasi master di atas: staff. Jangan mengasumsikan flag pelamar KIP-K berarti sudah menerima KIP-K. Untuk input massal gunakan template financial; tidak wajib mengisi UKT/IPK bila belum diketahui, kosong tetap null.

## Dashboard UKT/IPK dan buku digital

`GET /dashboard/ukt-ipk` — staff, tanpa body. Filter query:

| Field | Tipe/arti |
|---|---|
| tahun_lulus (atau angkatan) | Tahun 4 digit |
| perguruan_tinggi_id | ID kampus existing |
| program_studi, jalur_masuk | Nilai teks sesuai master/data |
| periode | Opsional periode progres; terbaru di dalam periode itu |
| beasiswa_id, status_beasiswa | ID program; proses/diterima/ditolak |
| penerima_kipk | 1/0 |
| ukt_min,ukt_max | Batas inklusif nominal rupiah |
| ukt_lt | Batas eksklusif: 2000000 berarti UKT di bawah Rp2 juta |
| ipk_min,ipk_max | 0–4, inklusif |

Respons 200 `{data:{as_of,filters,jumlah_alumni,ukt,ipk,per_kampus_prodi,tren_ipk,catatan}}`.
Statistik `ukt`/`ipk`: `{jumlah_diketahui,jumlah_belum_tercatat,minimum,maksimum,rata_rata}`. Semua hasil numerik null bila belum ada pengukuran; jangan tampilkan sebagai 0. Kelompok kampus/prodi juga berisi jumlah_penerima_kipk; tren berisi semester dan statistik IPK. Harga/status difilter sesudah memilih progres terakhir per siswa. Tren adalah riwayat kampus dari alumni yang termasuk snapshot, bukan sampel seluruh mahasiswa kampus.

`GET /buku-alumni/pdf?ukt_lt=2000000&tahun_lulus=2024` — staff, filter sama; 200 application/pdf, attachment `eightracer-buku-alumni.pdf`. FE harus memakai respons blob dan Authorization; jangan membuka URL tanpa token. 503 bila runtime PDF belum tersedia. Laporan agregat sekolah tidak memuat nama/NISN/kontak/penghasilan privat. Batasi filter bila cakupan melewati 10.000 record/1.000 kelompok. Dokumen untuk akses staff; publikasi buku beridentitas tetap memakai mekanisme persetujuan terpisah.

## Katalog, kriteria, ML dan rekomendasi

| Method/URL | Input | Output |
|---|---|---|
| GET /beasiswa | Staff; search,jenjang,page | Paginator katalog beserta kriteria |
| POST /beasiswa | Staff; nama_beasiswa,jenjang,deskripsi,penyelenggara,link_pendaftaran,tanggal_buka/tutup,is_active,program_eligibilitas_id opsional; kriteria:[{nama_kriteria,tipe_kriteria,operator,nilai_kriteria,bobot}] | 201 `{data:beasiswa}`; tipe/operator dibatasi server |
| PUT /beasiswa/{id} | Staff; payload lengkap katalog+kriteria | 200 data; kriteria diganti dalam transaksi |
| GET /siswa/{id}/rekomendasi-beasiswa/preview | Staff; query target_jenjang SMA/Kuliah | 200 `{data:{target_jenjang,pemeringkatan_ml,kandidat}}`; tidak memanggil Gemini/menulis draft |
| POST /siswa/{id}/rekomendasi-beasiswa/generate | Staff; JSON target_jenjang opsional | 201 `{data:draft}` dengan checklist/penjelasan/metadata ranking |
| PATCH /rekomendasi-beasiswa/{id}/review | Staff; status_review disetujui/ditolak,catatan_admin opsional | 200 data; 409 sumber berubah; 422 kriteria belum lengkap/tidak ada kandidat eligible |
| GET /rekomendasi-beasiswa/saya | Siswa, tanpa ID siswa | Paginator hasil disetujui milik sendiri; beasiswa snapshot, checklist, alasan,persiapan,skor_kecocokan,pemeringkatan_ml |

`skor_kecocokan` 0–100 adalah skor kecocokan kriteria; `pemeringkatan_ml.skor_kemiripan` 0–1 adalah kesamaan teks. Keduanya bukan persentase peluang lolos. Belum ada deskripsi/minat yang cukup -> fallback, similarity null; jangan membuat badge AI yakin. Data baru tidak otomatis menjadi label training beasiswa.

## Chat beasiswa

Siswa: `POST /chat-beasiswa`. Staff: `POST /siswa/{id}/chat-beasiswa`.

```json
{"message":"Apakah saya memenuhi syarat program ini?","beasiswa_id":1,"target_jenjang":"Kuliah"}
```

beasiswa_id/target_jenjang opsional; ID contoh bukan program nyata. history dan siswa_id dalam body ditolak. Response:

```json
{
  "data":{
    "answer":"Jawaban berdasarkan fakta yang tersimpan...",
    "status":"...",
    "llm_used":false,
    "beasiswa_id":1,
    "eligibility":{"skor":0,"lengkap":false,"memenuhi":false,"checklist":[]},
    "historical_evidence":{"status":"insufficient_data"},
    "sources":[{"id":"catalog:1","label":"Katalog beasiswa sekolah","url":null}],
    "context":"Persiapan jenjang berikutnya"
  }
}
```

Contoh disederhanakan, bukan hasil siswa nyata. Jika katalog kosong/ambigu, respons 200 menjelaskan kekurangan dan bisa memiliki struktur lebih ringkas; FE tampilkan answer dan periksa field opsional. Render answer sebagai teks biasa, bukan HTML mentah. Kegagalan Gemini tetap 200 dengan fallback faktual dan llm_used=false; jawaban belum berarti rekomendasi final disetujui BK. Endpoint stateless dan tidak menyediakan streaming/history percakapan.

## Status kegagalan dan penanganan FE

- 401: token hilang/tidak berlaku; arahkan login. 403: peran/izin/email belum memenuhi. Jangan sekadar menyembunyikan tombol; backend tetap memeriksa.
- 404: record milik pihak lain/tidak ditemukan. 409: konflik/stale/sudah direview/digest salah atau batch kedaluwarsa; muat ulang/preview ulang.
- 422: validasi, batas cakupan, data wajib belum ada. Biasanya `{message,errors:{field:[pesan]}}`; jangan mengandalkan stack trace.
- 429: tunggu sebelum mencoba lagi. Preview/commit/PDF masing-masing dibatasi 3/menit, chat 5/menit per pengguna ditambah limiter global API.
- 503: dependensi pemindai/PDF/provider pada alur yang memerlukannya belum tersedia. Jangan tampilkan rincian server/key.

Semua contoh tanpa secret. Inventaris 114 route beserta controller/middleware adalah hasil refleksi kandidat teruji, bukan bukti endpoint tersebut sudah terpasang di laptop utama. Lihat status pemasangan paket.

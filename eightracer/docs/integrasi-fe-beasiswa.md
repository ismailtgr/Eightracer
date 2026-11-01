# Kontrak backend rekomendasi beasiswa untuk FE

Backend Laravel berada di `C:\eightracer\eightracer`. FE memakai aplikasinya sendiri.
Halaman login/dashboard buatan agen sudah dihapus; jangan
menggunakan `/masuk`, `/beasiswa`, atau `/beasiswa-ui/api/*` untuk integrasi.

## Menjalankan backend

```powershell
.\php-project.ps1 artisan serve --host=127.0.0.1 --port=8000
```

MySQL lokal berjalan pada port 3306, database `eightracer_db`. Migration metadata
Gemini sudah diterapkan. `.tools/php84` adalah runtime lokal yang diabaikan Git;
tidak mengubah PHP XAMPP global. Key Gemini hanya di `.env` backend dan tidak boleh
dimasukkan ke source FE, variabel VITE_/NEXT_PUBLIC_, file contoh, maupun Git.

Base URL lokal: `http://127.0.0.1:8000/api`.
Untuk perangkat/hosting lain, gunakan base URL server yang bisa dijangkau FE.

## Autentikasi

Kirim `Accept: application/json` dan `Content-Type: application/json`.

`POST /login` menerima `username` dan `password` sesuai akun yang sudah tersedia.
Respons 200 berisi `message`, `user` (termasuk `id`, `username`, `role`), dan `token`.
Tambahkan `Authorization: Bearer <token>` pada request berikutnya.
`GET /me` untuk identitas saat ini; `POST /logout` mencabut token saat ini.
Login saat ini mencabut token lama, mengikuti AuthController yang sudah ada.
Pengaturan lengkap login Google, NISN, dan kebijakan sesi PRD adalah pekerjaan
auth terpisah dan tidak boleh dianggap selesai oleh fitur beasiswa ini.

Role aktual DB: `administrator` = Admin Sekolah, `siswa` = Siswa. Role `guru`
tidak diberi akses mengelola beasiswa. Jangan memakai `admin_sekolah` sebagai
nilai role API sebelum ada migrasi role yang disepakati tim.
Pendaftaran publik tidak dapat membuat akun administrator.

## Daftar endpoint

| Metode | Path setelah `/api` | Role | Respons sukses |
|---|---|---|---|
| GET | `/beasiswa?page=1` | administrator | 200, paginator katalog |
| POST | `/beasiswa` | administrator | 201, `{data: beasiswa}` |
| PUT | `/beasiswa/{id}` | administrator | 200, `{data: beasiswa}` |
| DELETE | `/beasiswa/{id}` | administrator | 200, `{message: ...}`; mengarsipkan |
| POST | `/siswa/{id}/rekomendasi-beasiswa/generate` | administrator | 201, `{data: rekomendasi}` dengan status pending |
| GET | `/rekomendasi-beasiswa?page=1` | administrator | 200, paginator seluruh hasil/draft |
| PATCH | `/rekomendasi-beasiswa/{id}/review` | administrator | 200, `{data: rekomendasi}` |
| GET | `/rekomendasi-beasiswa/saya?page=1` | siswa | 200, paginator hasil sendiri yang disetujui |

Paginator: baca array `data`, `current_page`, `last_page`, `total`, `next_page_url`,
dan `prev_page_url`. Katalog 20 entri/halaman, rekomendasi 15 entri/halaman.
Daftar siswa menggunakan endpoint siswa yang sudah dimiliki tim, bukan endpoint
UI agen. Periksa aturan akses endpoint domain siswa secara terpisah.

## Body katalog

POST dan PUT sama-sama menerima seluruh data wajib. PUT mengganti seluruh daftar
kriteria, bukan patch satu kriteria. Minimal satu dan maksimal 30 kriteria.

```json
{
  "nama_beasiswa": "Beasiswa Contoh",
  "penyelenggara": "Penyelenggara Contoh",
  "deskripsi": "Data uji, bukan program nyata",
  "jenjang": "SMA",
  "tanggal_buka": null,
  "tanggal_tutup": null,
  "link_pendaftaran": "https://example.org/beasiswa",
  "is_active": true,
  "kriteria": [
    {"nama_kriteria":"Nilai TKA minimum","tipe_kriteria":"nilai_tka","operator":">=","nilai_kriteria":"80","bobot":1}
  ]
}
```

Jenjang: `SMA`, `Kuliah`, `Keduanya`. Nilai tanggal `YYYY-MM-DD` atau null.
Kriteria memakai tipe: `nilai_tka`, `rata_rapor`, `penghasilan_orang_tua`,
`persentase_kehadiran`, `jumlah_prestasi`, `status_eligible`.
Operator angka: `=`, `!=`, `>=`, `<=`, `>`, `<`; status hanya `=`/`!=`.
`nilai_kriteria` dikirim sebagai string; bobot positif 0.01–999.99 atau null
(default penilaian 1). Nama hubungan pada respons katalog adalah
`kriteria_kelayakan_beasiswa`.

DELETE tidak menghapus riwayat atau detail rekomendasi. Reactivate memakai PUT
dengan `is_active=true` dan body lengkap.

## Generate dan review

Generate menerima body `{}`. FE perlu loading state selama provider memproses,
timeout request FE lebih dari 90 detik, dan menonaktifkan klik ganda.
Batas endpoint generate: 5 request/menit per pengguna.

Hasil generate ada di `response.data`, dengan `id`, `siswa_id`,
`tanggal_rekomendasi`, `status_review`, `ai_model`, `prompt_version`, dan
`rekomendasi_beasiswa_detail` (array). Tiap detail memuat:

- `beasiswa_id`, `skor_kecocokan` (decimal string, misalnya `"100.0000"`), `is_recommended`.
- `alasan_rekomendasi` (teks), `persiapan` (array teks).
- `beasiswa_snapshot`: nama, jenjang, penyelenggara, tanggal dan tautan saat dibuat.
- `evaluasi_kriteria`: `skor`, `lengkap`, `memenuhi`, `checklist`.
- Checklist: `kriteria`, `operator`, `syarat`, `status`.

Tampilkan teks AI sebagai teks biasa, bukan HTML. Validasi protokol tautan hanya
http/https sebelum membuka tautan dari data historis.

Checklist status: `terpenuhi`, `belum_terpenuhi`, `belum_diketahui`.
Skor 0–100 adalah kecocokan syarat, bukan persentase peluang lolos. Semua kriteria
adalah syarat wajib. Satu syarat gagal tidak dapat ditutup oleh skor tinggi.

Review:

```json
{"status_review":"disetujui","catatan_admin":"Sudah ditinjau sekolah."}
```

Atau `status_review="ditolak"`. Hanya draft pending dapat ditinjau satu kali.
Publikasi mensyaratkan semua checklist diketahui dan minimal satu kandidat yang
memenuhi seluruh syarat. Jika sumber berubah sejak generate, status 409 meminta
generate ulang. Hasil lama tidak diedit diam-diam.

Mapping label FE: `pending` → Draft, `disetujui` → Terpublikasi,
`ditolak` → Ditolak. Jangan menampilkan draft kepada siswa atau membuat tombol
publish di halaman siswa; backend tetap memeriksa role.

## Bentuk respons siswa

```json
{
  "current_page": 1,
  "data": [{
    "id": 10,
    "tanggal_rekomendasi": "2026-11-02T00:00:00.000000Z",
    "status": "Terpublikasi",
    "beasiswa": [{
      "beasiswa_id": 1,
      "informasi": {"nama_beasiswa":"Beasiswa Contoh","jenjang":"SMA"},
      "skor_kecocokan": "100.0000",
      "memenuhi_syarat": true,
      "checklist": [{"kriteria":"nilai_tka","operator":">=","syarat":"80","status":"terpenuhi"}],
      "alasan": "Memenuhi syarat nilai minimum yang tercatat.",
      "persiapan": ["Siapkan dokumen pendukung."]
    }]
  }],
  "last_page": 1,
  "total": 1
}
```

Contoh ini disederhanakan; paginator Laravel juga mengirim metadata tambahan.
Array kosong adalah state belum ada hasil terpublikasi, bukan error. Siswa yang
belum terhubung ke record siswa menerima 404. Sumber hash dan catatan admin tidak
dikirim ke siswa. Halaman siswa membaca hasil tersimpan, tidak memanggil Gemini.

## Error dan CORS

| HTTP | Tindakan FE |
|---|---|
| 401 | Token kosong/tidak valid; arahkan pengguna ke login FE |
| 403 | Role tidak berhak; sembunyikan aksi admin dan tampilkan larangan akses |
| 404 | Record tidak ditemukan atau akun siswa belum terhubung |
| 409 | Draft sudah ditinjau atau sumber berubah; muat ulang/generate ulang |
| 422 | Tampilkan `errors` per field jika ada, selain itu `message` data belum cukup |
| 429 | Terlalu banyak request; tunggu sebelum mencoba lagi |
| 503 | Konfigurasi/provider/kuota/output AI bermasalah; draft tidak dibuat parsial |

Error API selalu JSON, termasuk request tanpa Accept. Format umum `{message}`;
validasi memiliki `{message, errors: {field: [pesan]}}`.

CORS default pengembangan mengizinkan origin localhost/127.0.0.1 port 5173 dan
3000. Bila FE memakai origin lain, set variabel backend `FRONTEND_URLS` berisi
daftar origin tepat, dipisahkan koma, lalu `php-project.ps1 artisan config:clear`.
Misalnya `FRONTEND_URLS=https://fe.example.org`. Jangan memakai wildcard untuk
deployment. Integrasi ini Bearer token: tidak membutuhkan cookie, withCredentials,
atau sanctum/csrf-cookie. Key Gemini tidak pernah dikirim ke browser FE.

## Batas tahap implementasi

Generate saat ini untuk siswa SMA dengan riwayat kelas aktif tingkat 10–12.
Katalog Kuliah dapat dikelola, tetapi rekomendasi persiapan kuliah/alumni belum
diaktifkan. Durasi/periode perkiraan katalog dan pola alumni berizin masih
memerlukan schema/aturan tambahan. Endpoint ini tidak menjanjikan seluruh PRD
auth atau frontend sudah selesai.

Contoh request siap pakai ada di `docs/beasiswa-api.http`.

## Status verifikasi Gemini

Key lokal sudah dikonfigurasi dan dibersihkan dari kedua file contoh.
Layanan memakai `gemini-3.1-flash-lite` melalui Interactions API, dengan
`store=false`, JSON schema, dan thinking level minimal. Satu permintaan penjelasan
beasiswa sintetis berhasil dan hasilnya lolos validasi backend. Tidak ada data
siswa nyata yang dipakai untuk pengujian koneksi tersebut.

`GEMINI_TIMEOUT` dapat mengatur timeout backend (default 90 detik).
PHP portabel sudah dilengkapi bundle CA untuk verifikasi HTTPS. Tidak ada
penonaktifan verifikasi sertifikat.

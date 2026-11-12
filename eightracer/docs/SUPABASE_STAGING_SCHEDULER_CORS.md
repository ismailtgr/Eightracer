# Kesiapan Supabase staging, scheduler dan CORS

## Status yang sudah diterapkan

- Driver PDO PostgreSQL di PHP lokal 8.4 aktif. File .tools/php84/php.ini tidak di-commit.
- config/database.php menggunakan SSL require untuk pgsql dan menerima DB_SSLROOTCERT serta DB_EMULATE_PREPARES.
- .env.example dan .env.production.example mencerminkan Postgres dan QUEUE_CONNECTION=sync. Host/password pada template kosong; username direct postgres, shared pooler postgres.PROJECT_REF.
- Endpoint POST /api/internal/run-scheduler meminta header X-Scheduler-Secret. Secret kosong/tidak terkonfigurasi/kurang dari 32 karakter ditolak 403. Nilai melalui query string tidak diterima. Berhasil 200 {ok:true}, bentrok eksekusi 409, batas 6 request/menit/IP, kegagalan 503 tanpa output perintah.
- Secret lokal dibuat sekali di .env secara privat, tidak dicetak. SCHEDULER_SECRET pada template tetap kosong. Generate secret produksi terpisah melalui password manager tim; jangan taruh di URL layanan cron.
- Lock cache 300 detik mencegah trigger paralel. Task purge harian juga memakai withoutOverlapping(5). Lock file berlaku per instance; beberapa instance memerlukan cache bersama yang mendukung atomic lock.
- CORS hanya membaca FRONTEND_URLS, dipisahkan koma. FRONTEND_URL tidak digunakan. Tanpa FRONTEND_URLS di production, origin tidak diizinkan; localhost bukan fallback produksi. Daftarkan origin asli tanpa path /api. Next.js BFF memakai Bearer token ke BE, sehingga supports_credentials=false dipertahankan.
- SANCTUM_STATEFUL_DOMAINS bukan pengganti FRONTEND_URLS dan bukan syarat tunggal login BFF Bearer. Tidak ada perubahan komponen visual FE.
- 8 tests / 34 assertions lulus pada database testing terisolasi, schedule:run dimock. Tidak menjalankan purge data sekolah untuk pengujian.

## Migrasi aktif selesai — 12 November 2026

Backend utama di `C:\eightracer\eightracer` sudah memakai PostgreSQL Supabase project `hitxegwqiezzomhibpwf`, versi 17.11. Session pooler yang diverifikasi: `aws-0-ap-southeast-1.pooler.supabase.com:5432`, TLS require. Aplikasi memakai role khusus `eightracer_runtime` tanpa superuser, CREATE ROLE, CREATE DATABASE, ownership tabel, atau BYPASSRLS. Kredensial hanya disimpan dalam berkas privat yang dikecualikan Git.

- 57 migration Laravel berhasil pada tujuan yang semula kosong. Tidak mengubah migration lama dan tidak menjalankan migrate:fresh.
- Snapshot sumber: 63 tabel / 2.344 baris (termasuk 57 catatan migration). Sebanyak 2.287 baris aplikasi dipindahkan; 57 catatan migration tujuan dihasilkan Laravel, nama migration diverifikasi sama.
- Isi semua 63 tabel dibandingkan berdasarkan seluruh kolom sumber (nama migration untuk repository migration): cocok. Data sumber dicek ulang sebelum cutover dan tidak berubah.
- Jumlah penting: siswa 874; study_tracer 600; school_scholarship_observations 574; perguruan_tinggi 58; alumni_progress 8; basis_data_beasiswa 2; users 2. Angka tabel bukan seluruhnya jumlah alumni unik.
- ID, foreign key, hash password, APP_KEY dan isi terenkripsi dipertahankan. Sequence diatur berdasarkan ID hasil impor. MySQL tidak dihapus atau diperbarui.
- RLS diaktifkan untuk semua 63 tabel; anon/authenticated/PUBLIC tidak mendapat akses tabel atau sequence. Policy runtime hanya untuk koneksi server Laravel; otorisasi siswa/admin tetap dikerjakan backend. Security advisor Supabase: tidak ada lint.
- Login kedua role, endpoint alumni, profil, rekomendasi tersimpan, analitik dan dashboard berhasil pada PostgreSQL. PDF analitik menghasilkan HTTP 200 application/pdf. Role siswa ditolak 403 pada endpoint admin.
- CRUD katalog diuji langsung pada PostgreSQL dan di-rollback; jumlah katalog tetap dua. Pengujian runtime cache INSERT/UPDATE/DELETE juga di-rollback.
- Pencarian kini memakai whereLike/orWhereLike bawaan Laravel: tetap menghasilkan ILIKE di PostgreSQL dan dapat diuji dengan SQLite terisolasi. Ini memperbaiki delapan kegagalan pengujian akibat operator ILIKE mentah pada SQLite.
- Suite otomatis: 213 tests, 1.840 assertions, tidak ada failure/error; satu pengujian XLSX dilewati oleh prasyarat lingkungan. Suite memakai SQLite memory; bukti PostgreSQL berasal dari migration, verifikasi isi, CRUD rollback, dan HTTP checks di atas.
- Local dan Tailscale: FAQ 200 JSON; /me tanpa token 401 JSON. Server PHP di-restart setelah driver PostgreSQL diaktifkan.

Snapshot, konfigurasi sebelum cutover, hasil perbandingan dan hasil HTTP berada di `.migration-private/`, bukan untuk Git atau dikirim kepada FE. Tidak ada perubahan visual frontend pada tahap migrasi ini. Render/Vercel belum dideploy oleh tahap ini.

### Pengoperasian setelah cutover

Aplikasi biasa membaca `.env` (role runtime). Migration berikutnya memakai role migrator melalui `.env.staging`:

    .\.tools\php84\php.exe artisan migrate --env=staging --force
    .\.tools\php84\php.exe scripts\secure-supabase-backend.php

Jalankan pengamanan sesudah migration yang membuat tabel baru agar role runtime mendapat hak tabel baru dan RLS/policy yang sesuai. Jangan memberi runtime hak administrasi hanya agar migrate berjalan. Jangan menjalankan ulang impor pada tujuan berisi data; skrip menolaknya. Untuk mengulang staging di project lain, gunakan parameter dan rencana terpisah, jangan menyalin secret.

Rollback koneksi memerlukan pemulihan `.env` dari `.migration-private/env-before-supabase-20261112`, config:clear, dan restart backend. Jika sudah ada perubahan data baru di Supabase, rekonsiliasi dahulu sebelum kembali ke MySQL; jangan menghilangkan perubahan tersebut.

## Referensi konfigurasi koneksi

File .env.staging sekarang sudah terisi koneksi migrator proyek yang diverifikasi dan diabaikan Git. .env.staging.example tetap template publik tanpa secret.

Untuk Session pooler gunakan host exact dari Connect, port 5432, user postgres.PROJECT_REF. Untuk direct gunakan db.PROJECT_REF.supabase.co, port 5432, user postgres; direct memerlukan konektivitas IPv6 kecuali ada IPv4 add-on. Jangan menggabungkan host direct dan username pooler.

Jika memilih Transaction pooler port 6543, DB_EMULATE_PREPARES harus true. Untuk migration dan verifikasi awal gunakan direct atau Session pooler. Tetap DB_SSLMODE=require; gunakan verify-full dan DB_SSLROOTCERT bila sertifikat CA telah disediakan.

Jalankan dari folder backend:

    .\.tools\php84\php.exe scripts\verify-supabase-staging.php

Verifier berdiri sendiri: membaca .env.staging, tidak mem-boot Laravel dengan konfigurasi lokal, tidak mengganti .env, tidak menjalankan migration/seed/import, tidak mencetak kredensial. Verifier membuka transaksi repeatable-read READ ONLY, membaca versi PostgreSQL dan jumlah baris tabel utama bila ada, lalu rollback. Koneksi gagal dikembalikan sebagai kode generik tanpa exception/DSN privat.

## Checklist migrasi berikutnya (referensi, cutover lokal sudah selesai)

1. Pastikan fingerprint tujuan dan status staging sesuai proyek yang diizinkan; periksa tabel/data yang sudah ada.
2. Backup MySQL dan simpan APP_KEY yang sekarang; jangan generate ulang APP_KEY untuk data terenkripsi yang dipindahkan.
3. Jalankan migration pada staging yang telah disetujui. Jangan migrate:fresh pada database yang memiliki data. Migration lama tidak diedit pada pekerjaan ini; kegagalan Postgres dilaporkan dahulu.
4. Pindahkan data dengan mempertahankan primary key, relasi, JSON, boolean, tanggal, hash password dan ciphertext. Reset sequence PostgreSQL sesuai ID hasil transfer. Verifikasi hitungan dan checksum data serta relasi sebelum cutover.
5. Uji pencarian, login, CRUD, impor, analitik dan PDF pada Postgres. Pengujian SQLite tidak cukup membuktikan ilike/enum/change bekerja di Postgres.
6. Baru ubah DB_CONNECTION dan parameter koneksi .env aktif setelah target lulus verifikasi, lalu config:clear dan restart BE. Pertahankan DB MySQL sebagai sumber rollback sampai penerimaan uji selesai.

## Cron eksternal

Jadwalkan POST setiap menit ke base HTTPS BE + /api/internal/run-scheduler dengan header X-Scheduler-Secret serta Accept: application/json. Jangan menjalankan endpoint melalui browser/query secret dan jangan gunakan secret yang ditulis di dokumentasi. Cache persisten/shared diperlukan sesuai jumlah instance. schedule:run hanya menjalankan task yang jatuh tempo; respons 200 bukan bukti semua task berhasil. Monitor hasil task/cleanup secara terpisah.

Rujukan: https://supabase.com/docs/guides/database/connecting-to-postgres dan https://laravel.com/docs/12.x/scheduling.

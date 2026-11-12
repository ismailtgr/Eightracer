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

## Yang belum selesai

Database aktif tetap MySQL, tidak diubah menjadi database Postgres kosong. .env.staging belum berisi DB_HOST, DB_USERNAME dan DB_PASSWORD nyata. Koneksi staging Supabase, migration PostgreSQL dan perpindahan isi database belum diuji. Konfigurasi pgsql yang tersedia tidak membuktikan koneksi aktif telah berpindah. Query ilike yang sudah ada tidak kompatibel dengan MySQL aktif; penyelesaiannya menunggu cutover Postgres, tidak diubah kembali pada pekerjaan ini.

## Isi staging secara privat

Buka .env.staging dalam proyek utama, isi dari Supabase Dashboard > Connect. File tersebut diabaikan Git. .env.staging.example merupakan template publik tanpa secret.

Untuk Session pooler gunakan host exact dari Connect, port 5432, user postgres.PROJECT_REF. Untuk direct gunakan db.PROJECT_REF.supabase.co, port 5432, user postgres; direct memerlukan konektivitas IPv6 kecuali ada IPv4 add-on. Jangan menggabungkan host direct dan username pooler.

Jika memilih Transaction pooler port 6543, DB_EMULATE_PREPARES harus true. Untuk migration dan verifikasi awal gunakan direct atau Session pooler. Tetap DB_SSLMODE=require; gunakan verify-full dan DB_SSLROOTCERT bila sertifikat CA telah disediakan.

Jalankan dari folder backend:

    .\.tools\php84\php.exe scripts\verify-supabase-staging.php

Verifier berdiri sendiri: membaca .env.staging, tidak mem-boot Laravel dengan konfigurasi lokal, tidak mengganti .env, tidak menjalankan migration/seed/import, tidak mencetak kredensial. Verifier membuka transaksi repeatable-read READ ONLY, membaca versi PostgreSQL dan jumlah baris tabel utama bila ada, lalu rollback. Koneksi gagal dikembalikan sebagai kode generik tanpa exception/DSN privat.

## Langkah setelah koneksi terverifikasi

1. Pastikan fingerprint tujuan dan status staging sesuai proyek yang diizinkan; periksa tabel/data yang sudah ada.
2. Backup MySQL dan simpan APP_KEY yang sekarang; jangan generate ulang APP_KEY untuk data terenkripsi yang dipindahkan.
3. Jalankan migration pada staging yang telah disetujui. Jangan migrate:fresh pada database yang memiliki data. Migration lama tidak diedit pada pekerjaan ini; kegagalan Postgres dilaporkan dahulu.
4. Pindahkan data dengan mempertahankan primary key, relasi, JSON, boolean, tanggal, hash password dan ciphertext. Reset sequence PostgreSQL sesuai ID hasil transfer. Verifikasi hitungan dan checksum data serta relasi sebelum cutover.
5. Uji pencarian, login, CRUD, impor, analitik dan PDF pada Postgres. Pengujian SQLite tidak cukup membuktikan ilike/enum/change bekerja di Postgres.
6. Baru ubah DB_CONNECTION dan parameter koneksi .env aktif setelah target lulus verifikasi, lalu config:clear dan restart BE. Pertahankan DB MySQL sebagai sumber rollback sampai penerimaan uji selesai.

## Cron eksternal

Jadwalkan POST setiap menit ke base HTTPS BE + /api/internal/run-scheduler dengan header X-Scheduler-Secret serta Accept: application/json. Jangan menjalankan endpoint melalui browser/query secret dan jangan gunakan secret yang ditulis di dokumentasi. Cache persisten/shared diperlukan sesuai jumlah instance. schedule:run hanya menjalankan task yang jatuh tempo; respons 200 bukan bukti semua task berhasil. Monitor hasil task/cleanup secara terpisah.

Rujukan: https://supabase.com/docs/guides/database/connecting-to-postgres dan https://laravel.com/docs/12.x/scheduling.

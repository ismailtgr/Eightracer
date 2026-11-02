# Pengamanan backend Eightracer

Patch ini menyiapkan kontrol backend untuk aplikasi sekolah. Status pemasangan dan hasil tes ada dalam laporan audit; kode yang lulus tes belum membuktikan server deployment aman.

## Perubahan kontrak untuk FE

- API tetap Bearer token. Token admin maksimal 72 jam; login admin baru mencabut perangkat lama. Token siswa/guru maksimal 7 hari dan bisa beberapa perangkat. Token lama tanpa expiry ditolak: pengguna perlu login ulang.
- Endpoint master siswa, keluarga, impor, dan progres/timeline alumni hanya untuk `administrator`. Akun `guru` belum mempunyai cakupan kelas yang disetujui sehingga akses data pribadi tersebut ditutup. Endpoint `/rekomendasi-beasiswa/saya` tetap khusus hasil milik siswa yang login dan telah disetujui admin.
- `/register` mengembalikan 403 sampai tersedia proses verifikasi identitas sekolah. NISN saja tidak dipakai sebagai bukti kepemilikan akun. Akun sekolah harus diprovisikan admin dengan password acak individual yang di-hash; jangan gunakan password bersama.
- Input berupa teks biasa, panjang dan angka dibatasi; HTML/control character ditolak. Password dipertahankan persis, tidak di-trim atau dibersihkan sebagai HTML. FE tetap wajib memakai text rendering/escaping, tidak memasukkan data API/Gemini ke `innerHTML`, `v-html`, atau `dangerouslySetInnerHTML`. JSON dari backend tidak bisa menjamin cara FE menampilkannya aman.
- Unggahan impor menerima template `.xlsx` saja, maksimal 5 MB, 2.000 baris data (ditambah maksimum 100 baris header), 10 sheet, 52 kolom, 50.000 sel, arsip yang terurai maksimal 20 MB dan 500 entri. Formula, macro, embedded binary, XML entity/DOCTYPE, tautan eksternal, dan koordinat berlebihan ditolak. CSV/XLS sebelumnya ditutup karena kontrak impor menggunakan nama worksheet. File tidak diterbitkan atau disimpan ke direktori publik.
- Seluruh unggahan dipindai ClamAV lokal sebelum diproses. Jika scanner tidak tersedia atau hasil tidak tegas bersih, impor ditolak (503); hasil terinfeksi ditolak (422). Pemindai nyata dan signature terbarunya harus dipasang/diuji di server.
- Kesalahan internal API memakai pesan umum tanpa SQL, jalur server, stack, atau API key. Log API mencatat jenis exception dan pola route, tanpa request body/binding SQL. Pesan validasi berisi aturan input saja.
- Impor tidak lagi menganggap alumni memberikan persetujuan publikasi; `izinkan_tampil_di_buku=false`. Nama yang cocok dengan lebih dari satu siswa ditolak untuk mencegah salah pemilik. Konfirmasi persetujuan publikasi harus menjadi proses tersendiri.

## Pemulihan password

Endpoint baru: `POST /api/forgot-password` menerima `email`; `POST /api/reset-password` menerima `email`, `token`, `password`, dan `password_confirmation`.

Pemulihan default **nonaktif**. Aktifkan setelah SMTP yang sesungguhnya, URL HTTPS FE, dan email pemulihan terverifikasi tersedia. Tidak ada endpoint publik untuk menetapkan `email_verified_at`. Sekolah harus memverifikasi kepemilikan alamat melalui proses tepercaya sebelum pengelola mengisi email/penanda verifikasi. Jangan menandai alamat terverifikasi hanya karena pengguna mengetikkannya. Proses verifikasi email mandiri dan UI FE belum dibuat.

`PASSWORD_RESET_ENABLED=true`, `PASSWORD_RESET_FRONTEND_URL=https://domain-sekolah/reset-password`, dan mailer bukan `log`/`array` diperlukan. URL reset berasal dari konfigurasi, bukan header Host. Respons permintaan tautan sama untuk akun tidak ada dan email belum terverifikasi. Token broker disimpan sebagai hash, berlaku 30 menit, sekali pakai; reset mencabut seluruh token dan sesi akun. Password baru minimal 12 karakter, huruf besar/kecil, angka, simbol; maksimal 72 byte untuk mencegah pemotongan bcrypt. FE jangan menyimpan token reset di analytics/log, hapus query setelah dibaca, dan gunakan `Referrer-Policy: no-referrer`.

## Pemasangan patch dan migrasi

Cadangkan database terenkripsi sebelum migrasi. Terapkan file patch, lalu gunakan PHP **8.4.1 atau lebih baru** sesuai lock dependency; runtime portable lokal saat ini 8.4.27. Jalankan dari folder aplikasi:

```powershell
.\php-project.ps1 C:\ProgramData\ComposerSetup\bin\composer.phar install --no-interaction
.\php-project.ps1 artisan migrate --force
.\php-project.ps1 artisan optimize:clear
```

Migrasi tambahan hanya menambahkan email pemulihan, penanda verifikasi, remember token, dan tabel reset. Tidak menjalankan `migrate:fresh`, menghapus data sekolah, ataupun membuat password default. Instalasi produksi memakai `composer install --no-dev --optimize-autoloader`; jalankan test pada tahap build sebelum menghapus dependency pengembangan.

Gunakan `.env.production.example` sebagai daftar pengaturan, isi secret pada `.env` server/secret manager. File contoh sengaja tidak berisi key/password. Jangan menimpa `.env` lokal secara otomatis. Saat `APP_ENV=production`, aplikasi memaksa debug mati dan cookie secure/HttpOnly/encrypted; API menolak HTTP. HTTPS tetap harus benar di web server. Jika memakai reverse proxy, daftarkan hanya proxy yang benar-benar dipercaya, jangan mempercayai semua IP agar header HTTPS tidak bisa dipalsukan. Document root web server wajib folder `public`, bukan root repo. Blok `.env`, `.git`, backup, log, dan dump pada web server; jangan gunakan PHP development server untuk produksi.

Jalankan `php artisan security:check` setelah konfigurasi produksi terpasang. Perintah memeriksa nilai efektif tanpa mencetak secret dan mencoba scan contoh teks aman. Exit nonzero menandakan konfigurasi belum siap. Pemeriksaan ini bukan verifikasi firewall, grants, SMTP delivery, atau umur signature ClamAV.

## Database privat dan izin minimum

Konfigurasi lokal sekarang masih memakai `root` tanpa password dan debug lokal aktif. Ini belum cocok untuk deployment. Jangan menerbitkan port 3306. Jika DB satu mesin, bind listener ke loopback; jika terpisah gunakan jaringan privat dengan firewall hanya dari aplikasi dan TLS, bukan IP publik. Uji dari mesin di luar jaringan bahwa port tidak terbuka.

Buat user runtime khusus dengan host spesifik dan password acak kuat melalui pengelola rahasia. Berikan hanya `SELECT, INSERT, UPDATE, DELETE` pada `eightracer_db.*`; jangan beri `GRANT OPTION`, `FILE`, `PROCESS`, `SUPER`, atau hak global. Gunakan akun migrasi tersendiri untuk DDL saat deployment, lalu cabut/nonaktifkan kredensial migrasinya dari proses aplikasi. Verifikasi `SHOW GRANTS` dan binding/firewall pada server nyata. Patch ini tidak mengganti password root atau grant database yang sedang digunakan.

## ClamAV dan operasional

Jalankan `clamd` dengan socket Unix privat atau TCP loopback (`CLAMAV_ENDPOINT=tcp://127.0.0.1:3310`). Jangan mengekspos socket scanner ke internet. Aktifkan pembaruan signature dan pemantauan kegagalannya, batasi scan size/recursion/time, sesuaikan stream limit minimal 5 MB. Aktifkan pelaporan file melampaui batas pemindaian sehingga tidak dianggap bersih. Uji file uji antivirus standar di lingkungan uji dengan prosedur pengelola server; tes di paket ini hanya meniru balasan protokol, tidak memasang antivirus.

Matikan `display_errors`, aktifkan `log_errors`, dan matikan `expose_php` pada PHP produksi. Atur PHP/web server `upload_max_filesize=5M`, `post_max_size=6M`, batas request, waktu proses dan memori yang wajar. Lindungi folder temporary/storage dengan izin user aplikasi saja. Jadwalkan `sanctum:prune-expired --hours=24`, `auth:clear-resets`, dan pembersihan temporary import. Gunakan cache limiter yang persisten/bersama (database/Redis), bukan array; HTTPS FE dan origin CORS harus persis. Jangan mengaktifkan debug/request-body logging pada proxy, APM, atau error tracker. Siapkan backup terenkripsi, retensi data sekolah, uji restore dan monitoring akses admin.

## Hasil dan keterbatasan verifikasi

Pengujian memakai SQLite in-memory dan data sintetis. Integrasi Gemini/mock SMTP tidak mengirim data sekolah atau email sungguhan. Uji konkurensi pada MySQL produksi, FE escaping, pengiriman email, pemindai antivirus nyata, dan uji akses jaringan masih diperlukan. Satu tes impor XLSX penuh dilewati otomatis bila pemeriksaan `is_readable` PHP rusak di lingkungan Windows terkelola; tes itu tetap disertakan untuk dijalankan ulang pada server tujuan. Jangan menghapus pemeriksaan keamanan pustaka Excel untuk mengatasi pembatasan lingkungan ini.

Rujukan: [Laravel Sanctum](https://laravel.com/framework/docs/12.x/sanctum), [Laravel password reset](https://laravel.com/framework/docs/12.x/passwords), [protokol ClamD](https://docs.clamav.net/manual/Usage/ClamdProtocol.html), [OWASP file upload](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html). Dependency Symfony MIME diperbarui ke 7.4.22 sesuai [advisory resmi](https://symfony.com/blog/cve-2026-45070-email-header-injection-via-non-token-characters-in-mime-parameter-names).

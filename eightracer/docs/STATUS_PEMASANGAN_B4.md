# Status pemasangan B4 — TERPASANG di proyek utama

**Status layar FE terbaru:** pengguna meminta tampilan beserta data dummy asli FE dahulu. Source prototype b4 sudah dipulihkan di `C:/eightracer/frontend`; pratinjau tidak mengambil data atau sesi backend. Data asli baru ditampilkan pada bagian yang diminta berikutnya. Pemasangan backend dan database yang dijelaskan di bawah tetap tersedia untuk integrasi selanjutnya.

Pada 3 November 2026, 76 berkas paket B4/ML diterapkan ke `C:/eightracer/eightracer` setelah hash sumber dan berkas awal diperiksa. Cadangan kode dan database dibuat lebih dahulu; backup database terenkripsi berhasil diuji balik. APP_KEY dan GEMINI_API_KEY dipertahankan.

Migration `2026_11_02_000004_complete_b4_workflows` sudah **Ran, batch 5** pada database XAMPP asli `eightracer_db`, port 3306. Tabel sekarang 62. Data tetap 872 profil, 599 penerimaan PT, 58 perguruan tinggi, 17 prestasi, dan 2 akun uji; tidak ada impor ulang atau data sekolah buatan.

122 tes kode utama dijalankan pada SQLite in-memory: 121 lulus, 1 legacy dilewati, 853 assertion. Uji layanan nyata membuktikan login Admin/Siswa, penolakan akses data sekolah untuk Siswa, dashboard UKT/IPK, template impor, serta PDF agregat yang valid. Uji ML ditahan dengan validasi 422 karena sumber akademik/katalog belum lengkap.

Konfigurasi lokal yang aktif: APP_DEBUG=false, HTTPS diwajibkan, proxy hanya loopback, akun DB aplikasi dengan SELECT/INSERT/UPDATE/DELETE tanpa DDL atau akses akun sistem, sesi terenkripsi/secure/HttpOnly, Caddy HTTPS, Mailpit untuk email uji, worker dan scheduler. ClamAV memakai signature resmi terverifikasi; berkas bersih lolos dan sampel uji aman EICAR terdeteksi melalui INSTREAM dari RAM.

FE sudah dipindah ke `C:/eightracer/frontend`, mempertahankan repo teman dan riwayat `e45fc9e`, kemudian diperbarui melalui commit `e820c63`. Build Webpack, TypeScript dan 14 tes FE lulus di lokasi baru. Folder output produksi dipisahkan dari dev di Windows. Buka `http://127.0.0.1:3000/admin` atau workspace `C:/eightracer/Eightracer.code-workspace`.

Bukti privat lokal berada di `C:/eightracer/.local`: backend-installation.json, runtime-configuration.json, backend-tests.xml, integration-verification.json, security-verification.json, mail-verification.json. Kata sandi uji berada di `.local/akun-uji/Akun_Sementara.txt`; tidak masuk Git.

Belum siap produksi: hosting/domain, Google client ID, email sekolah dan SMTP pengirim terverifikasi. Rapor, katalog/kriteria beasiswa, serta UKT/IPK masih menunggu data asli. ML terpasang sebagai TF-IDF/cosine, bukan model peluang lolos yang sudah tervalidasi. Diagram versi 20261102 sudah ada di docs/diagrams; revisi empat tabel workflow terbaru belum diklaim selesai.

Pemeriksaan ulang menemukan repo backend di **C:/eightracer**, dengan kode Laravel pada subfolder `eightracer/`. Backend B4 sudah tercatat dalam commit **237f8d5**. Catatan lama tentang kegagalan menulis `.git/index.lock` tidak lagi menggambarkan status commit lokal saat ini. Status push tetap perlu dibedakan dari pemasangan lokal.

Tampilan FE telah dipulihkan mengikuti acuan b4 `b693384` pada branch `codex/fe-tampilan-asli`, dengan koneksi API dan batas akses tetap aktif. CSS asli dipertahankan; 18 tes FE lulus. Formulir awal yang belum menyediakan seluruh data wajib diberi keterangan/ditahan, bukan menyimpan data buatan. Detail ada di `frontend/docs/PEMULIHAN_TAMPILAN_FE.md` pada root proyek utama.

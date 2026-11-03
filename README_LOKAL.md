# Eightracer — proyek utama

Semua pekerjaan kode yang aktif kini berada di folder ini:

| Bagian | Lokasi |
| --- | --- |
| Backend Laravel dan ML B4 | `eightracer/` |
| Frontend dari repo tim, branch B4 | `frontend/` |
| Diagram draw.io dan Enterprise Architect | `eightracer/docs/diagrams/` |
| Akun uji, bukti pemeriksaan, log dan cadangan privat | `.local/` dan `eightracer/.tools/` (diabaikan Git) |

Buka **Eightracer.code-workspace** di Antigravity untuk melihat backend dan frontend. Kedua repo tetap terpisah, masing-masing mempertahankan remote dan riwayatnya.

Web lokal: **http://127.0.0.1:3000/admin**. Pratinjau dapat dibuka tanpa login; klik **Akses data asli** untuk akun sekolah. Kata sandi sementara berada di `.local/akun-uji/Akun_Sementara.txt`. Siswa uji tidak terhubung ke siswa asli.

Untuk menyalakan kembali dari PowerShell di folder ini, jalankan `./Start_Eightracer.ps1`. MySQL XAMPP port 3306 harus berjalan. FE memakai Node.js yang terpasang di laptop; backend memakai PHP 8.4 dalam proyek. Email pemulihan lokal ditangkap Mailpit dan tidak dikirim ke internet.

Pemasangan B4 sudah dilakukan: migration 000004 batch 5, 62 tabel; 872 profil, 599 penerimaan PT, 58 perguruan tinggi, 17 prestasi dan 2 akun uji tetap terjaga. PDF, template impor, API UKT/IPK, serta batas akses Admin/Siswa sudah diuji. ClamAV, HTTPS, akun database terbatas dan debug mati sudah diterapkan.

Rapor, katalog/kriteria beasiswa dan UKT/IPK masih menunggu data nyata. ML sudah terpasang tetapi belum boleh diklaim akurat atau menghasilkan peluang lolos; belum tersedia data untuk evaluasi. Google Login dan email produksi memerlukan kredensial resmi. Detail ada di `eightracer/docs/STATUS_PEMASANGAN_B4.md`.

Commit FE terbaru adalah e820c63, melanjutkan e45fc9e. Perubahan BE yang dipasang saat ini terlihat di **Changes**, tetapi belum di-commit/push karena alat tidak mendapat izin menulis metadata `.git` backend. Tidak ada `.env`, token, password, database sekolah atau private key yang ditambahkan ke Git.

# Hasil perbaikan B4 — 11 November 2026

## Lingkungan yang dipakai pengguna
- Frontend: http://localhost:3000
- Backend frontend: http://127.0.0.1:8000/api
- Database: eightracer_db (data asli sekolah).
- Pengalihan sementara ke database uji sudah dibatalkan. Data sekolah tidak dihapus/diganti.
- Pemeriksaan database: 872 profil siswa, 599 penerimaan PT, 58 kampus, 574 catatan sumber beasiswa, 2 program katalog. Jumlah catatan sumber bukan jumlah program katalog.
- Login akun admin.uji dan siswa.uji diuji melalui /api/auth/login dan /api/auth/me pada localhost:3000: HTTP 200; data admin/siswa juga HTTP 200.
- Kata sandi akun lama tetap berlaku; kredensial disimpan pribadi di C:/eightracer/.local/akun-uji/Akun_Sementara.txt.

## Perbaikan kode
- Form progres: input semester menggantikan daftar panjang 1–40; batas backend tetap 1–40, bukan kebijakan baru.
- Beasiswa progres menggunakan checkbox tanpa tombol Ctrl. Input rupiah tidak memakai ikon mata yang menimpa angka.
- Timeline menggunakan pencarian nama bawaan FE; dropdown tambahan Pilih Alumni dihapus. Identitas tetap ada jika progres kosong.
- Analitik memakai struktur rentang UKT FE; nominal belum diketahui tidak dibuat nol dan tidak memenuhi halaman dengan baris kosong.
- Distribusi IPK dihitung dari progres terbaru dengan batas kelompok yang jelas; nilai null tidak dihitung sebagai nol.
- LocalStudentPreview hanya berlaku untuk akun siswa pada lingkungan local, flag aktif dan koneksi loopback. Menampilkan data alumni dasar/katalog asli tanpa kontak, NISN, keuangan atau skor personal buatan. Tidak mengubah izin publikasi sekolah.
- Label prodi id 581 dikembalikan persis sesuai sumber: S1 + PROFESI KEBIDANAN. Salinan nilai sebelumnya disimpan privat.
- Dua tautan registrasi publik di Navbar dihapus sesuai mekanisme akun disiapkan sekolah. Perubahan ini ada di folder proyek utama; Navbar sebelumnya memang belum dilacak Git.

## Verifikasi AI dan ML terpisah dari data sekolah
Database eightracer_b4_uji_20261105 hanya dipakai untuk uji teknis; localhost:3000 tetap database asli.
- Login tiga akun: berhasil.
- TF-IDF/cosine: applied; dua kandidat dengan kemiripan 0.77182051 dan 0.06123094. Nilai kemiripan bukan peluang menerima beasiswa.
- Gemini generate: HTTP 201; review admin: HTTP 200; siswa membaca hasil terpublikasi: HTTP 200.
- Siswa pembanding mengakses hasil milik siswa lain: HTTP 403.
- Chat pertama memakai jawaban cadangan; uji ulang berhasil HTTP 200 dan llm_used=true. Kegagalan sementara provider tetap ditangani tanpa membuat fakta.
- Siswa tidak dapat mengakses katalog CRUD khusus admin: HTTP 403 (sesuai pembatasan peran).

## Batas pemeriksaan
- Pemeriksaan TypeScript dan ESLint dilakukan untuk berkas yang berubah.
- Tes kontrak dan unit FE lulus; tes autentikasi FE yang memerlukan server hasil build belum dapat dijalankan karena hasil build produksi tidak tersedia. Login nyata diuji terpisah lewat FE yang sedang berjalan.
- Verifikasi visual browser belum dilakukan otomatis; HTTP berhasil tidak membuktikan seluruh layout sempurna.
- Perubahan kali ini tidak mengubah skema; tidak membutuhkan migration database sekolah.
- Dokumen audit use case mencatat selisih yang masih harus diselaraskan. Seluruh 64 skenario UAT belum dinyatakan lulus.
- Pengaturan hosting, domain/HTTPS produksi, SMTP produksi dan pemindaian antivirus pada server live tidak dinyatakan selesai hanya berdasarkan pengujian lokal.
- Tidak ada secret atau kredensial disertakan dalam commit.

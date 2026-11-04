# Tech Stack Eightracer — materi PPT

Diperiksa dari kode, paket, dan runtime proyek utama pada 4 November 2026.

| Bagian | Teknologi | Fungsi dalam Eightracer |
|---|---|---|
| Frontend | Next.js16 + React19 | Membangun halaman, komponen antarmuka, navigasi dan pengambilan data dari backend. |
| Bahasa FE | TypeScript | Memeriksa tipe data agar kontrak API dan komponen lebih konsisten. |
| Tampilan | Tailwind CSS4 + CSS | Mengatur layout, warna, ukuran dan tampilan responsif. |
| UI pendukung | Lucide React + Framer Motion | Menyediakan ikon dan animasi antarmuka. |
| Runtime FE | Node.js + npm | Menjalankan server frontend serta mengelola paket dan proses build. |
| Backend | PHP8.4 + Laravel12 | Menjalankan logika bisnis, validasi, autentikasi, pengelolaan data dan layanan B4. |
| Komunikasi | REST API / JSON | Menghubungkan frontend dengan layanan backend. |
| Database | MariaDB10.4 | Menyimpan data siswa, alumni, penerimaan PT, progres kuliah, katalog dan rekomendasi beasiswa. |
| Akses data | Eloquent ORM + Laravel Migrations | Mengelola relasi/query serta perubahan skema database. |
| Autentikasi | Laravel Sanctum + RBAC | Memverifikasi sesi dan membatasi hak Admin/Guru BK serta siswa. Token FE berada dalam cookie HttpOnly melalui server FE. |
| Impor data | Laravel Excel + parser CSV/XLSX B4 | Membaca spreadsheet sekolah, memvalidasi baris, menyediakan pratinjau dan menyimpan impor secara terkontrol. |
| Pemeriksaan kelayakan | Rule-based eligibility engine | Membandingkan data siswa dengan syarat nilai, ekonomi, jenjang dan kriteria katalog yang tercatat. |
| ML rekomendasi | Content-based filtering: TF-IDF + cosine similarity | Memberi urutan relevansi beasiswa berdasarkan kemiripan profil minat dengan deskripsi program. |
| AI generatif | Google Gemini API | Menyusun penjelasan dan saran persiapan serta mendukung chatbot berdasarkan konteks backend. |
| PDF dan evaluasi | Python + ReportLab | Membuat buku/laporan alumni PDF. Skrip Python lain mengevaluasi peringkat menggunakan Precision@K dan nDCG@K. |
| Keamanan unggahan | ClamAV | Memindai berkas sebelum data unggahan diproses. Validasi ukuran/tipe/konten dilakukan oleh backend. |
| Pengujian | PHPUnit11, Node.js Test Runner, TypeScript, ESLint | Menguji API/logika bisnis, parser/form, pemeriksaan tipe dan kualitas kode. |
| Kolaborasi | Git + GitHub | Mencatat versi, commit, branch dan pekerjaan tim. |
| Editor | Antigravity | Lingkungan pengembangan kode proyek. |
| Uji antarlaptop | Tailscale | Menghubungkan FE dan BE pada jaringan privat saat pengembangan dari laptop berbeda. |

## Alur rekomendasi B4

1. Backend membaca profil siswa dan kriteria katalog.
2. Rule-based eligibility mengecek kelengkapan dan kesesuaian syarat.
3. TF-IDF mengubah teks minat/deskripsi menjadi bobot kata; cosine similarity mengurutkan alternatif yang memenuhi kriteria dan datanya lengkap.
4. Gemini menyusun penjelasan hasil dan saran persiapan.
5. Hasil disimpan untuk ditinjau sesuai alur aplikasi.

Perhitungan TF-IDF/cosine berjalan lokal dalam PHP melalui TfidfScholarshipModel dan ScholarshipContentRanker. Python digunakan untuk PDF dan evaluasi offline. Hobi, cita-cita dan bidang prestasi terverifikasi membentuk dokumen minat.

Kalimat singkat untuk presentasi:
“Eightracer menggunakan pendekatan rekomendasi hybrid: rule-based untuk pemeriksaan kelayakan, content-based filtering dengan TF-IDF dan cosine similarity untuk pemeringkatan, serta Gemini untuk penjelasan rekomendasi.”

## Status ML yang akurat untuk dipresentasikan

Implementasi ML sudah terdapat di kode dan terhubung ke layanan rekomendasi. Pemeriksaan saat ini menunjukkan catalog_insufficient:0 deskripsi katalog yang dapat dipelajari,0 profil dengan sumber minat terstruktur, dan kualitas rekomendasi belum tervalidasi. Bila sumber belum cukup, sistem menggunakan hasil pemeriksaan kriteria. Skor cosine adalah kemiripan isi/minat. Evaluasi relevansi perlu penilaian Guru BK; belum ada angka akurasi yang dapat diklaim.

Runtime terverifikasi: PHP8.4.27, Laravel12.70.0, MariaDB10.4.32; Next.js16.4.3 terpasang. Versi utama lebih sesuai dicantumkan pada slide.

Sumber implementasi:
- frontend/package.json
- eightracer/composer.json
- app/Services/Beasiswa/TfidfScholarshipModel.php
- app/Services/Beasiswa/ScholarshipContentRanker.php
- app/Services/Beasiswa/ScholarshipRecommendationService.php
- app/Services/Beasiswa/GeminiScholarshipExplainer.php
- app/Services/School/BulkSpreadsheetReader.php
- tools/render_alumni_book.py
- tools/evaluate_scholarship_ranking.py

# Audit implementasi transkrip B4

2 November 2026. Baseline dibaca dari `C:\eightracer\eightracer`; perubahan dibuat dan diuji pada salinan terisolasi. Lihat `installation-status.json` dalam paket untuk memastikan apakah kandidat sudah dipasang. Status “ditambahkan” pada dokumen ini berarti ada kode dan bukti tes, bukan otomatis sudah live.

| Permintaan | Baseline proyek utama | Hasil kandidat | Yang masih memerlukan tindakan nyata |
|---|---|---|---|
| Siswa/profil/akun/rapor/asesmen/alumni/bantuan | Sudah ada model, tabel dan API terpisah | Dipakai kembali, tidak menduplikasi tabel inti | Cocokkan dan lengkapi sumber yang belum tersedia |
| Impor Excel/CSV semua modul, banyak tab | Import penerimaan PT dengan format sekolah khusus | Ditambahkan generic preview/commit untuk 7 modul; XLSX multi-tab + header mapping, transaksi dan audit | Worker, antivirus nyata; pemetaan referensi/sumber sekolah |
| Siswa login NISN+password | Login email; NISN untuk registrasi master | Ditambahkan identifier NISN hanya untuk akun siswa terhubung, hashing/OTP tetap berlaku | FE mengirim salah satu identifier; SMTP untuk aktivasi/reset |
| Admin/Guru BK akses sekolah | Administrator; role guru belum menjadi izin BK | Grant BK eksplisit, email verified + allowlist, revoke token | Email sekolah belum ada; tidak dibuat email/domain/akun palsu |
| Profil awal oleh siswa | Profil akun tersedia, master dikelola staff | Ditambahkan usulan own-profile dengan review dan stale guard | FE sambungkan formulir usulan dan antrean review |
| Filter universitas/prodi/jalur/tahun/beasiswa | Sebagian sudah di tracer/analitik | Dashboard baru filter UKT/IPK/status, memilih progres terbaru dahulu | Data progres kuliah aktual dan pengujian FE |
| FinancialData UKT/IPK/beasiswa | Sudah ada alumni_progress + detail beasiswa, revisi dan izin | Dipakai langsung; impor respons Forms dipetakan ke alumni/penerimaan | Respons biaya/IPK/beasiswa asli, bukan hanya penerimaan PT |
| Digital book PDF | Model/fitur tracer/buku parsial, belum endpoint PDF agregat | Ditambahkan endpoint unduh B&W, Python ReportLab, tanpa identitas privat | Python/ReportLab di server dan worker/process yang diizinkan |
| Chat AI terhubung profil+riwayat | Gemini untuk generate penjelasan rekomendasi | Ditambahkan chat RAG terarah, fakta backend, sumber, fallback | Uji provider live dengan konfigurasi yang berlaku; percakapan multi-turn/streaming belum disediakan |
| Kelayakan dan historis beasiswa | Evaluator syarat/review sudah ada | Reuse evaluator + agregat alumni berizin, ambang privasi, penanganan data kosong | Kriteria resmi dan hasil diterima/ditolak yang cukup; tidak ada peluang lolos buatan |
| Machine learning sebagai nilai BE | Paket TF-IDF sebelumnya belum terpasang | Paket ML diintegrasikan dalam kandidat, guard eligible, metadata+evaluator offline | Katalog/minat terstruktur dan penilaian relevansi BK untuk validasi manfaat |
| Skema/API/alur AI/rekomendasi stack | Dokumen PRD sebelumnya | Skema 62 tabel/515 kolom, DDL, inventaris 114 route, kontrak FE, panduan impor/operasional | Migration baru hanya setelah izin tulis; uji deployment dan FE bersama |

## Data yang sudah diketahui dari audit sebelumnya

Pemeriksaan terkini pada 2 November 2026 pukul 16:34 UTC memakai transaksi MySQL READ ONLY: 58 tabel/475 kolom, 872 profil siswa/alumni, 599 penerimaan PT, 58 referensi kampus dan 17 prestasi. Nilai rapor, asesmen formal, katalog/kriteria beasiswa dan progres UKT/IPK/beasiswa masing-masing masih 0 baris. Bukti lengkap ada di `readiness-actual.json` dalam paket. Pemeriksaan sebelumnya juga mencatat 17 prestasi pending dan 174 catatan peminatan kualitatif; pemeriksaan terbaru ini tidak mengurai ulang teks pribadi tersebut. Angka profil bukan jumlah akun aktif atau dataset training beasiswa. Kesiapan ML saat ini: 0 deskripsi yang dapat dipelajari dan 0 profil dengan sumber minat terstruktur.

Data sekolah tetap merupakan sumber sah. Prestasi pending berarti belum melalui review aplikasi. Catatan peminatan tidak dianggap tidak benar; tanggal/angka asesmen yang belum ada tidak dibuat-buat. Tidak ada data palsu diimpor, akun sekolah dibuat, atau API key dicantumkan dalam paket.

Data penerimaan PT memperkaya tracer dan konteks jalur masuk, tetapi tidak membuktikan biaya UKT, IPK kuliah, syarat beasiswa atau hasil seleksi beasiswa. Karena itu B4 memerlukan katalog resmi, ekonomi/rapor terstruktur, respons Forms finansial dan hasil beasiswa yang dipetakan dengan benar. Laporan `catalog_insufficient` adalah temuan kelengkapan, bukan keraguan pada sekolah.

## Bukti pengujian kandidat

- PHPUnit keseluruhan: 119 tes, 848 assertion; 118 lulus, 1 legacy test dilewati. Kasus baru B4: 25 tes/174 assertion. ML: 14 tes tambahan dari paket sebelumnya. SQLite in-memory, data sintetis, HTTP provider dipalsukan; MySQL sekolah tidak digunakan untuk tes.
- Impor XLSX baru diuji melalui endpoint penuh dengan beberapa tab, header yang berbeda, serial tanggal Excel dan NISN berawalan nol. Legacy importer memiliki satu test skip terkait pembacaan fixture host; jangan mengartikan ini sebagai verifikasi ulang seluruh format lama sekolah.
- Uji penting: siswa A tidak memilih profil B, grant/revoke BK, email tidak sah ditolak, rollback seluruh batch, batch expired/stale/idempotensi, ID Forms tidak boleh pindah alumni, antivirus tidak tersedia ditolak, null UKT/IPK tidak menjadi nol, riwayat lama tidak menggantikan keadaan terbaru.
- Chat diuji terhadap klaim angka dari LLM, ID tidak valid, quota/provider gagal, data historis kecil/tanpa izin, pemilihan nama program ambigu. Tidak mengklaim Gemini live sudah diuji.
- Endpoint PDF menjalankan renderer nyata dalam tes, hanya agregat; kegagalan runtime menjadi 503. Contoh PDF sintetis empat halaman dirender dan diperiksa visual seluruh halamannya, bukan laporan sekolah nyata.
- Sintaks PHP dan format berkas perubahan diperiksa. SQL tambahan dikompilasi memakai grammar MySQL tanpa koneksi. Migrasi diuji di SQLite; kompatibilitas operasi MySQL live belum dibuktikan sampai instalasi aktual dilakukan. Lima tes evaluator ranking Python juga lulus; ini verifikasi perhitungan metrik, bukan hasil akurasi sekolah.

## Status pemasangan aktual

Permintaan akses tulis ke app/config/routes/migrations/tests/docs/tools proyek utama tidak diberikan oleh pemeriksaan persetujuan otomatis; hanya akses jaringan yang diberikan, tanpa alasan penolakan terperinci. Karena itu 61 berkas kandidat belum disalin dan migration baru belum dijalankan. Database sekolah tidak diubah. Endpoint baru belum tersedia dari proyek utama; jangan meminta FE memakai endpoint baru sebelum pemasangan selesai. Berkas .env dan kode FE tidak disentuh.

Scan pola secret pada berkas produksi/dokumen/tools paket tidak menemukan pola key/token/private key yang dicari. Git native tidak mendapat izin membaca direktori proyek, sehingga pemeriksaan tracking/history .env belum dapat diselesaikan pada sesi ini. Ini bukan audit menyeluruh seluruh riwayat Git dan tidak menjamin tidak ada secret di commit lama.

## Urutan tindak lanjut

1. Pasang kandidat dan migration pada proyek utama setelah akses tersedia; jangan seed/drop/reset database.
2. Lengkapi katalog/kriteria resmi, minat terstruktur, tanggal asesmen asli dan respons finansial. Rekonsiliasi ID sebelum impor.
3. Tetapkan akun/email BK yang sah, SMTP, antivirus dan runtime PDF; uji alur login/reset/upload nyata.
4. FE integrasikan kontrak baru, login/izin, preview/commit/polling, dashboard, PDF dan chat. Uji bersama dari dua laptop melalui koneksi backend yang telah disepakati.
5. Validasi relevansi ML dengan BK pada holdout, lanjut uji deployment/HTTPS/CORS/database privat/backup. Model supervised penerimaan beasiswa belum dilatih karena label hasil yang memadai belum ada.

Tidak ada frontend baru, commit/push Git atau deployment hosting dalam perubahan ini. Diagram UML lama perlu disinkronkan setelah skema kandidat benar-benar dipasang; dokumen skema saat ini sudah menjelaskan hubungan baru, tetapi file EA/draw.io lama tidak diam-diam diklaim sudah diperbarui.

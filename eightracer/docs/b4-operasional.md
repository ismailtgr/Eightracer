# Menjalankan backend B4

Pemasangan aktual lihat `installation-status.json` di paket hasil. Pengujian terisolasi tidak mengubah MySQL sekolah. Panduan ini tidak mengandung kredensial.

## Runtime dan pemasangan

1. Pakai PHP yang memenuhi composer proyek: ^8.4.1 (runtime teruji 8.4.27); PHP XAMPP 8.2 tidak cukup. Dependensi Laravel/vendor yang sudah ada dipertahankan.
2. Cocokkan hash baseline, cadangkan berkas yang diganti dan database dengan akses terbatas. Salin hanya isi `files/` paket ke root proyek setelah izin tulis tersedia. Jangan menimpa perubahan baru tanpa membandingkannya.
3. Pada database existing yang sudah memiliki seluruh migration baseline, jalankan hanya migration baru:

```powershell
.\.tools\php84\php.exe artisan migrate --path=database/migrations/2026_11_02_000004_complete_b4_workflows.php --force
.\.tools\php84\php.exe artisan optimize:clear
```

`b4-schema-reference.sql` bukan skrip upgrade. Jangan menjalankan migrate:fresh, reset, seed atau drop tabel pada database sekolah. Empat tabel tambahan menyimpan alur kerja, bukan data latihan.

4. PDF memerlukan Python 3 dan ReportLab. Buat lingkungan Python milik proyek/hosting, lalu install requirement yang dipin:

```powershell
python -m venv .venv-pdf
.\.venv-pdf\Scripts\python.exe -m pip install -r tools/requirements-pdf.txt
```

Atur `B4_PYTHON_BINARY` ke executable Python lingkungan tersebut melalui environment deployment. Jangan memasukkan direktori Codex/runtime laptop ke kode produksi. Jika runtime belum tersedia, endpoint PDF mengembalikan 503 generik. Contoh PDF yang disertakan berisi data sintetis.

5. Jalankan worker queue untuk commit impor (misalnya database queue); konfigurasi service sesuai hosting:

```powershell
.\.tools\php84\php.exe artisan queue:work --tries=1 --timeout=300
.\.tools\php84\php.exe artisan schedule:run
```

Queue connection produksi harus asinkron (database/Redis), bukan sync untuk pekerjaan impor. Scheduler dipanggil tiap menit oleh sistem; `school:purge-import-plans` dijadwalkan harian untuk menghapus isi rencana kedaluwarsa. Metadata audit tetap ada. Pastikan tidak ada dua scheduler utama yang berjalan bersamaan.

## Pengaturan yang masih memerlukan data nyata

| Pengaturan | Nilai yang diperlukan | Perilaku jika belum siap |
|---|---|---|
| GEMINI_API_KEY | Secret di .env/environment server, tidak ditaruh kode/Git/FE | Chat memakai fakta deterministik; generate rekomendasi mengikuti guard provider yang sudah ada. |
| SCHOOL_EMAIL_DOMAINS atau SCHOOL_STAFF_EMAILS | Domain sekolah yang benar atau daftar email sekolah persis | Guru tidak mendapat akses BK. Saat audit pengguna belum memiliki email sekolah; dibiarkan kosong. |
| Grant BK | Administrator memberi grant ke akun guru yang emailnya terverifikasi dan diizinkan | Memiliki role guru saja tidak cukup; grant tidak tersedia lewat registrasi umum. |
| SMTP/pengirim email | Mailbox/layanan terverifikasi, kredensial server-side | OTP/reset melalui email belum dapat dinyatakan siap produksi. |
| Antivirus | ClamAV/service pemindai sesuai konfigurasi security proyek | Unggah/impor ditolak saat pemindai tidak tersedia; jangan mematikan guard agar lolos. |
| Hosting/domain/HTTPS | Domain FE/BE, TLS, origin CORS persis, proxy tepercaya | Local/Tailscale adalah lingkungan pengembangan, bukan bukti konfigurasi produksi. |
| Database | Host privat, akun aplikasi minimum CRUD yang diperlukan, akun migrator terpisah | Jangan membuka port database ke internet atau membagikan kredensial ke FE. |

Setelah email sekolah nyata tersedia: verifikasi akun guru melalui alur administrasi yang sah, isi allowlist, clear config cache, administrator memanggil `PUT /api/school-staff/{id}/bk-access`. Grant/revoke menghapus token lama sehingga pengguna perlu login kembali. Tidak ada pembuatan akun admin/BK otomatis.

## Data yang perlu dilengkapi sekolah/BK

- Katalog beasiswa aktif, deskripsi, link resmi, jenjang, masa berlaku dan kriteria nilai/penghasilan yang terstruktur.
- Rapor asli semester 1–5, ekonomi yang belum diketahui tetap null, bantuan terpetakan ke jenis yang benar.
- Respons Google Forms UKT/IPK/beasiswa dipetakan ke siswa/penerimaan dengan ID yang tepat. `external_id` harus stabil, bukan nomor urut Excel yang berubah saat sorting.
- Tanggal asesmen dan skor aslinya. Catatan gaya belajar/peminatan tidak diubah menjadi angka RIASEC/MI atau tanggal tes tebakan.
- Review prestasi sumber sekolah dan lengkapi bidang/minat terstruktur untuk ML. Pending menunjukkan proses pemeriksaan aplikasi.
- Izin analitik/publikasi dan hasil beasiswa diterima/ditolak yang cukup sebelum mengeluarkan proporsi historis. Data diterima PT saja tidak cukup.

## Validasi sebelum live

Periksa APP_ENV=production, APP_DEBUG=false, HTTPS, CORS spesifik, header keamanan, batas upload, private storage, pemindai antivirus, izin database, worker/scheduler, pengiriman OTP/reset dan pemulihan backup. Uji FE bersama akun siswa A/B dan admin/BK yang sah. Jangan simpan bearer token, API key atau dump sekolah dalam repositori. Tidak ada klaim semua butir produksi aman hanya karena tes otomatis lolos.

Evaluasi manfaat ML menggunakan penilaian BK dan data holdout; paket menyediakan evaluator offline, bukan angka akurasi hasil sekolah. Jalankan `php artisan ml:beasiswa-check --json` untuk kesiapan data. Periksa kembali rate limit, latensi, dan ukuran laporan dengan beban deployment sebenarnya.

# Integrasi backend B3 dan B4 ? 12 November 2026

Backend menggabungkan main tim (f314c17) dan B4 (c4f8684). Tidak ada perubahan desain atau komponen visual FE dalam merge ini.

## Kontrak API alumni

Semua URL di bawah memakai base `/api` dan Bearer token akun sekolah.

| Kegunaan | Endpoint | Respons utama | Akses |
|---|---|---|---|
| Direktori B3 | GET /alumni | data, meta, filter_options | Admin/Guru BK berwenang |
| Detail B3 | GET /alumni/{id} | objek detail langsung | Admin/Guru BK berwenang |
| Filter B3 | GET /alumni/filter-options | univ, prodi, jalur | Admin/Guru BK berwenang |
| Statistik B3 | GET /alumni/statistik | statistik agregat | Admin/Guru BK berwenang |
| Direktori B4 | GET /b4/alumni | data, total, per_page, current_page, filters bila diminta | Admin/Guru BK berwenang |
| Detail dan timeline B4 | GET /b4/alumni/{id} | data | Admin/Guru BK berwenang |
| Jejak alumni siswa | GET /jejak-alumni | data publik sesuai izin | Siswa terautentikasi |
| Progress lama | GET /alumni/{siswa}/timeline | data | Admin/Guru BK berwenang |
| Editor progress B4 | POST /alumni/{id}/college-progress | kontrak editor yang sudah ada | Admin/Guru BK berwenang |

Path editor, impor progress, analitik, katalog, rekomendasi, chat, profil dan login tetap mengikuti dokumentasi B4. ID pada detail B3/B4 dan college-progress adalah ID study_tracer; path progress lama memakai ID siswa. Jangan menukarkan keduanya.

Adapter FE lokal `/api/b4/alumni` meneruskan daftar/detail ke `/api/b4/alumni` backend. Editor progress tetap ke `/api/alumni/{id}/...`. Komponen dan tampilan milik FE tetap menjadi acuan. Kontrak readBackendAlumni berbasis `/study-tracer` dan `/jejak-alumni` tetap tersedia.

## Autentikasi dan privasi

Pendaftaran publik tetap dinonaktifkan sesuai keputusan sekolah; akun siswa diprovisikan admin. Login, /me, reset password dan sesi memakai implementasi B4. Form alumni B3 mengakses profil berdasarkan user login. Direktori staf tidak dibuka ke siswa karena respons detail B3 memuat NISN; gunakan Jejak Alumni untuk siswa.

## Migration dan pemasangan

Gunakan PHP 8.4.1+ sesuai composer.lock B4; versi 8.2 dari instruksi awal tim tidak memenuhi dependency saat ini. Jalankan composer install setelah mengambil merge, jangan mengganti .env atau APP_KEY instalasi yang sudah ada. Atur FRONTEND_URLS sesuai origin FE tim.

Jalankan php artisan migrate setelah backup database. Jangan gunakan migrate:fresh, refresh atau db:wipe pada database sekolah. Migration tahun_lulus dibuat kompatibel dengan kolom B4 yang sudah ada dan rollback migration ini tidak menghapus kolom milik migration sebelumnya. Tambahan kolom perguruan_tinggi mengikuti main B3. Migration mengubah skema; data siswa/alumni tidak dikirim oleh Git.

Impor mempertahankan mode update_skor B3, transaksi per baris, kesalahan aman, tidak mengosongkan skor jika sumber kosong dan tidak memberi persetujuan publik otomatis. Nama siswa ambigu tidak diperbarui massal.

## Validasi

205 pengujian backend / 1812 assertions lulus pada PHP 8.4.27 dengan SQLite in-memory. Termasuk kontrak B3 dan B4 bersamaan, migrasi baru dan kolom lama, pembatasan akses siswa, impor skor serta seluruh pengujian CRUD/auth/rekomendasi/AI yang tersedia. Layanan Gemini, SMTP dan antivirus pada pengujian menggunakan mock; hasil ini tidak menyatakan layanan eksternal live selalu tersedia. Data sekolah tidak digunakan dalam test RefreshDatabase.

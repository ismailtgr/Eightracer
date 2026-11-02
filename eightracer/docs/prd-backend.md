# Backend Eightracer sesuai PRD

Implementasi 2 November 2026. Aplikasi Laravel berada di `C:\eightracer\eightracer`. Dokumen ini menjadi acuan kontrak backend terbaru, termasuk pengganti login username dan reset melalui tautan pada dokumentasi lama. UI dikerjakan tim frontend.

## Menjalankan dan menguji

Gunakan PHP proyek, bukan PHP 8.2 dari XAMPP. Dependency terkunci memerlukan PHP >=8.4.1; runtime proyek yang tersedia adalah 8.4.27.

```powershell
cd C:\eightracer\eightracer
.\php-project.ps1 artisan serve --host=127.0.0.1 --port=8000
.\php-project.ps1 tests/run.php
```

`tests/run.php` dan `Tests\TestCase` memaksa SQLite dalam memori. Pengujian tidak mengosongkan database sekolah. Untuk migrasi gunakan `artisan migrate`, jangan `migrate:fresh` pada database sekolah. Migrasi PRD mempertahankan data lama dan menambah struktur akun, OTP, asesmen, kriteria, persetujuan publikasi serta revisi progres. Pembatalan migrasi ini memakai backup dan migrasi koreksi yang ditinjau, karena penghapusan kolom dapat menghilangkan riwayat.

## Konvensi FE

- Base URL lokal: `http://127.0.0.1:8000/api`. Produksi wajib HTTPS.
- Kirim `Accept: application/json`; request JSON memakai `Content-Type: application/json`. Upload memakai multipart.
- Setelah login, kirim `Authorization: Bearer <token>`. Hindari menyimpan token di URL, log, analytics, atau repository. Tangani 401 dengan kembali ke login.
- Role database/API tetap `administrator`, `siswa`, dan role lama `guru`. Label FE boleh “Admin Sekolah”; jangan mengirim `admin_sekolah` sebagai role baru. Modul admin hanya menerima `administrator`; akun guru tidak mendapat akses admin.
- 401: login ulang; 403: tidak berhak/belum terverifikasi; 404: data tidak tersedia; 409: benturan/versi berubah; 422: input atau syarat proses belum terpenuhi; 429: pembatasan percobaan; 503: layanan pendukung belum tersedia.
- Error input menyediakan `errors` per field. Jangan menampilkan HTML dari nilai API; gunakan teks biasa. Respons API tidak memuat detail SQL, file server, atau kunci layanan.
- Daftar memakai pagination Laravel. Perhatikan endpoint lama siswa memiliki pembungkus `data` di luar paginator; endpoint baru umumnya langsung paginator. Contoh request siap edit: `prd-api.http`.

## Autentikasi

| Endpoint | Input dan hasil |
|---|---|
| `POST /register` | `full_name`, `email`, `nisn` 10 digit, `password`, `password_confirmation`. Hanya siswa. 202: `challenge_id`, `expires_in`. |
| `POST /register/verify` | `challenge_id`, `otp` enam digit. 201: user, token, expiry. |
| `POST /login` | `email`, `password`. 200: `user`, `token`, `expires_at`. |
| `POST /forgot-password` | `email`. Respons umum dan `challenge_id`, baik akun ditemukan maupun tidak. |
| `POST /forgot-password/verify` | `challenge_id`, `otp`. Hasil: challenge grant baru, `reset_token`, `expires_in`. |
| `POST /reset-password` | challenge grant, `reset_token`, `password`, `password_confirmation`. Mematikan seluruh token dan sesi lama. |
| `POST /auth/google/challenge` | Mendapat `client_id`, `challenge_id`, `nonce` untuk Google Identity Services. |
| `POST /auth/google` | `challenge_id`, `id_token`; calon siswa baru juga mengirim `nisn`. |
| `POST /logout` | Membatalkan token perangkat yang dipakai. |
| `GET /me` | Profil akun sendiri. |

Sekolah membuat master siswa lebih dahulu, termasuk `registration_email`. Pendaftaran hanya mencocokkan siswa yang belum diklaim dengan NISN **dan** email tersebut; OTP membuktikan kepemilikan email. NISN saja tidak cukup. Nama akun tidak menimpa nama pada biodata sekolah.

Password baru minimal 12 karakter, huruf besar/kecil, angka dan simbol; maksimal 72 byte. OTP berlaku 10 menit, maksimal lima kesalahan per challenge, sekali pakai. Meminta OTP baru membatalkan challenge lama untuk email/tujuan yang sama. Batas permintaan tiga kali per 15 menit per email, dengan batas IP tambahan. Grant reset berlaku lima menit dan juga sekali pakai. OTP disimpan sebagai HMAC; payload pendaftaran serta job notifikasi dienkripsi.

Admin hanya satu token aktif dan maksimal 72 jam. Login baru membatalkan token dan sesi sebelumnya. Siswa dapat beberapa perangkat dengan token maksimal tujuh hari. Token tanpa expiry dan email belum terverifikasi ditolak.

Google harus menggunakan nonce dari challenge backend saat FE memulai login. Backend memeriksa signature RS256 dari kunci Google, issuer, audience/client ID, waktu berlaku, nonce, subject, dan email terverifikasi. Challenge hanya sekali pakai. Gmail/Workspace yang otoritatif dapat menghubungkan akun yang emailnya sudah terverifikasi; akun admin tetap harus sudah disediakan. Email Google dari penyedia pihak ketiga memerlukan login manual lalu `POST /profile/google` dengan `current_password`, challenge baru dan ID token. Pendaftaran Google tidak membuat admin. Rujukan: [verifikasi ID token Google](https://developers.google.com/identity/gsi/web/guides/verify-google-id-token).

## Profil

Semua endpoint profil membutuhkan login dan hanya mengakses pemilik token.

| Endpoint | Kegunaan |
|---|---|
| `GET /profile`, `PATCH /profile` | Lihat akun; ubah `full_name`. Role/email/user_id ditolak pada update ini. |
| `POST /profile/email/otp` | Email baru dan `current_password`; kirim OTP ke email baru. |
| `POST /profile/email/verify` | `challenge_id`, `otp`; ganti email, lepas hubungan Google lama, batalkan sesi. |
| `PUT /profile/password` | Password baru + konfirmasi + `current_password`. |
| `POST /profile/password/otp` | Akun Google tanpa password meminta OTP sebelum membuat password pertama. Kirim challenge dan OTP pada endpoint password. |
| `POST /profile/photo`, `GET /profile/photo` | Upload/read foto sendiri. JPEG/PNG/WebP <=2MB, batas piksel, antivirus, dan penulisan ulang PNG. |

## Data murid dan ekonomi — admin

`/siswa` menyediakan CRUD master. Pembuatan master tidak memerlukan akun siswa; `user_id` boleh kosong. NISN 10 digit harus unik. Pencarian/filter: `search`, `kelas_id`, `angkatan` (tahun lulus), `jenis_kelamin`, `per_page` <=100. Detail siswa memuat keluarga, minat dan riwayat kelas; profil akun terpisah.

- `POST /siswa/{id}/kelas`: `kelas_id`, `tanggal_mulai`, opsional `keterangan`. Menutup kelas sebelumnya dan menyimpan histori perpindahan; tanggal harus sesudah awal kelas lama.
- `PUT /siswa/{id}/minat`: array ID `hobi` dan `cita_cita`, termasuk array kosong untuk mengosongkan pilihan.
- `GET /siswa/{id}/data-pendukung`: biodata pendukung, nilai, presensi, organisasi, karakter, prestasi, saudara, minat dan kelas.
- `POST/PUT/DELETE /siswa/{id}/pendukung/{type}[/{record_id}]`: `karakter`, `organisasi`, `prestasi`, atau `saudara`. Record wajib milik siswa di URL. Bobot prestasi diatur pada program, bukan pada input prestasi.
- `PUT /siswa/{id}/ekonomi`: array `orang_tua` tepat dua record (`ayah`, `ibu`), opsional object `wali`, dan array `bantuan_ids`. Kondisi: `hidup`, `meninggal`, `bercerai`. Wali wajib bila salah satu kondisi orang tua bukan hidup. Operasi disimpan sekaligus; penghasilan kosong tetap tidak diketahui.
- Endpoint lama GET/POST `orang-tua`, `wali`, `media-sosial`, `bantuan` tersedia. Kondisi orang tua selain hidup membutuhkan wali yang sudah tercatat pada alur bertahap.
- Input bantuan memakai `jenis_bantuan_id`, `nomor_penerima`, `tanggal_mulai`, `tanggal_selesai`, `aktif` sesuai SQL. Field lama `tahun`/`nominal` bukan kolom tabel bantuan.
- `PUT/DELETE /siswa/{id}/keluarga/{type}/{record_id}` mengelola record `bantuan` atau `media-sosial` milik siswa.
- `GET/POST /referensi/{type}` dan `PUT/DELETE /referensi/{type}/{id}`: `kelas`, `mata-pelajaran`, `jenis-bantuan`, `hobi`, `cita-cita`, `perguruan-tinggi`. Hapus referensi yang masih dipakai ditolak.

## Asesmen dan eligibilitas

`POST /siswa/{id}/asesmen` menyimpan asesmen baru: `tanggal_asesmen`, `tipe_kepribadian`, `gaya_belajar`, `iq` opsional, enam skor `riasec`, dan array `multiple_intelligence` (`jenis_kecerdasan`, `skor`). Koreksi memakai `koreksi_dari_id`, tetap menyimpan record lama. Admin membaca `/siswa/{id}/asesmen`; siswa membaca `/asesmen/saya` saja.

`PUT /siswa/{id}/nilai-rapor` menerima `semester` 1–5 dan `nilai` berisi mapel/nilai 0–100. Penyimpanan memperbarui pasangan siswa-mapel-semester, bukan menggabungkan semester. `PUT /siswa/{id}/presensi` menerima semester dan jumlah hadir/sakit/izin/alpa; server menghitung persentase.

`PATCH /siswa/{id}/kelengkapan` menerima boolean `prestasi_dikonfirmasi`, `organisasi_dikonfirmasi`, `karakter_dikonfirmasi`, `bantuan_dikonfirmasi`. Gunakan setelah admin memeriksa bahwa daftar kosong memang berarti tidak ada; tanpa konfirmasi, kekosongan tidak dianggap nol.

`GET/POST /program-eligibilitas`, `PUT/DELETE /program-eligibilitas/{id}` mengelola program; DELETE mengarsipkan. Setiap perubahan menaikkan versi. `POST /program-eligibilitas/{id}/hitung` dengan `siswa_ids` <=100 menyimpan hasil historis. `GET /program-eligibilitas/{id}/hasil` menghitung status terkini. Siswa memakai `GET /eligibilitas/saya`, yang hanya memuat ringkasan komponen tanpa ambang internal atau data ekonomi.

Program membutuhkan `nama`, array `kriteria`; tiap kriteria berisi `nama_kriteria`, `tipe_kriteria`, `operator`, `nilai_kriteria` berupa string, dan bobot opsional. Komponen: TKA, rerata rapor semester 1–5, penghasilan kedua orang tua, kehadiran terakhir, jumlah prestasi terverifikasi, organisasi, poin karakter, skor prestasi dan syarat bantuan sosial. Seluruh syarat wajib dipenuhi. Skor adalah persentase bobot syarat yang terpenuhi, bukan probabilitas diterima. Data kurang menghasilkan **Belum dinilai**.

Untuk `skor_prestasi`, isi `bobot_prestasi` untuk Sekolah/Kabupaten/Kota/Provinsi/Nasional/Internasional dan pilih `metode_prestasi`: `tertinggi` atau `jumlah_dibatasi_100`. Hanya prestasi terverifikasi yang dihitung. Untuk `bantuan_sosial_sesuai`, isi `bantuan_ids`, pilih `mode_bantuan` (`salah_satu`/`semua`), lalu gunakan syarat numeric 1. Hanya bantuan aktif pada tanggal pemeriksaan yang dihitung. Kebijakan ini dipilih admin, tanpa bobot bawaan yang dianggap kebijakan sekolah.

## Rekomendasi jurusan

- Admin mengatur `/klaster-jurusan` (GET/POST, PUT/DELETE per ID). Bobot komponen `nilai`, `riasec`, `multiple_intelligence` wajib berjumlah 100. Komponen bernilai positif membutuhkan data terkait; bobot mapel untuk komponen nilai juga berjumlah 100.
- Nilai: rata-rata tiap mapel dari semester 1 sampai semester yang diminta, lalu bobot mapel. RIASEC: proporsi kode relevan yang cocok dengan tiga kode siswa. MI: rerata skor jenis kecerdasan relevan. Hasil merupakan gabungan bobot yang ditetapkan admin; bukan prediksi diterima di PT.
- `POST /siswa/{id}/rekomendasi-jurusan/generate` dengan semester 1–5 membuat batch pending. Klaster dengan data kurang tidak diberi skor seolah data lengkap.
- `GET /rekomendasi-jurusan` untuk admin; `PATCH /rekomendasi-jurusan/{batch_id}/review` menerima `disetujui`/`ditolak` dan catatan. Sumber berubah: 409 dan generate ulang.
- `GET /rekomendasi-jurusan/saya` hanya batch terbaru yang disetujui, tanpa catatan internal admin.

## Rekomendasi beasiswa Gemini

Katalog `/beasiswa` hanya admin: nama, jenjang SMA/Kuliah/Keduanya, durasi+satuan, periode apply, tanggal buka/tutup, tautan resmi dan kriteria. DELETE mengarsipkan. Gunakan `program_eligibilitas_id` untuk kriteria status atau komponen program. Program yang diarsipkan tidak memasok status kelayakan.

Pencarian katalog: `GET /beasiswa?search=nama&jenjang=SMA&page=2`. Search maksimal 100 karakter dan dapat digabungkan dengan jenjang. SMA/Kuliah juga menyertakan Keduanya; filter Keduanya hanya Keduanya. Pagination 20 record mempertahankan filter. Input query tidak sah ditolak 422.

`POST /siswa/{id}/rekomendasi-beasiswa/generate` membuat draft AI. `target_jenjang` opsional SMA/Kuliah; siswa SMA dapat meminta konteks persiapan kuliah. Jenjang kuliah saat ini berasal dari progres aktif/cuti pada penerimaan PT yang diterima; IPK dan semester berasal dari progres terbaru. Nilai yang belum diketahui tetap kosong.

Backend menghitung skor dan checklist. Gemini hanya menyusun penjelasan dan langkah persiapan berdasarkan kandidat; tidak mengubah skor. Tidak ada fine-tuning/model sekolah yang dilatih. Request tidak mengirim nama, NISN, kontak, atau angka penghasilan siswa; penyimpanan interaksi provider dinonaktifkan. Katalog adalah sumber beasiswa, bukan hasil AI mengarang beasiswa baru. Respons tidak valid/kuota/provider gagal tidak menyimpan draft setengah jadi.

Admin meninjau lewat `PATCH /rekomendasi-beasiswa/{id}/review`. Draft dengan sumber berubah atau data kriteria kurang tidak dapat disetujui. Siswa memakai `/rekomendasi-beasiswa/saya`, hanya hasil disetujui. Snapshot menjaga informasi saat publikasi meskipun katalog kemudian berubah. Ringkasan pola alumni hanya berasal dari snapshot agregat yang berizin dan sudah dipublikasikan admin.

## Study tracer, progres, timeline dan analitik

- `/study-tracer` CRUD admin. Bisa memakai `siswa_id` atau `alumni_baru` (`nama_lengkap`, `tahun_lulus`, NISN/gender opsional). Tidak membuat NISN, akun, atau tanggal sekolah palsu.
- Data PT: perguruan tinggi, strata, prodi, jalur/subjalur, status penerimaan; SNBP membutuhkan nilai rapor, SNBT membutuhkan UTBK, Mandiri mengikuti subjalur. Prestasi terhubung harus milik alumni yang sama.
- `POST /alumni/{siswa_id}/progress`: `study_tracer_id`, `periode`, `semester`, `tahun_lulus`, `tanggal_update`, status pendidikan, IPK, KIP-K, UKT, IPI dan array beasiswa opsional. ID penerimaan harus milik siswa tersebut. IPK 0–4; IPI nominal uang dan hanya untuk jalur Mandiri. Tidak membuat beasiswa dummy.
- Periode baru membuat entri baru. Koreksi `PATCH /alumni-progress/{id}` dengan data lengkap membuat revisi baru, menandai versi lama tidak current, dan meminta publikasi ulang. Riwayat lama tetap ada. Periode duplikat ditolak 409.
- `PATCH /study-tracer/{id}/publikasi` membutuhkan `is_published`, referensi izin dan konfirmasi izin. Perubahan data penerimaan mencabut publikasi sampai ditinjau ulang.
- `PATCH /alumni-progress/{id}/publikasi` juga membutuhkan `publish_financials`, sehingga izin identitas dan izin nominal per periode terpisah. Mencabut izin dapat dilakukan tanpa konfirmasi baru.
- Siswa memakai `/jejak-alumni` dan `/jejak-alumni/{id}`. Hanya penerimaan/progres berizin. Tidak memuat WA, NISN, keluarga atau catatan internal; nominal hanya jika diizinkan pada periode tersebut. Prestasi yang dicantumkan kini tersedia sebagai array `prestasi` pada proyeksi penerimaan dan timeline, dengan field publik terbatas; URL bukti privat dan prestasi milik siswa lain tidak disertakan.
- Timeline admin: `/alumni/{siswa_id}/timeline`; timeline siswa ada pada detail jejak alumni. Menggunakan tanggal sebenarnya. Jika hanya tahun diketahui, tampilkan tahun; jangan mengubahnya menjadi 1 Januari.
- `/analitik-beasiswa` dan `/analitik-beasiswa/export` hanya admin. Filter: angkatan, prodi, jenis PT, beasiswa, periode. Ringkasan memakai progres terbaru per siswa dalam filter; tren memakai periode, tanpa menghitung ulang revisi lama. CSV melindungi sel formula. Tiap `tren_periode` juga memuat array `beasiswa` dengan jumlah alumni unik serta hitungan status `proses`, `diterima`, `ditolak`. Duplikasi PT memakai status terbaru per alumni-beasiswa-periode. Filter beasiswa membatasi tren dan distribusi sesuai pilihan. Detail DTO dan aturan terdapat di `perbaikan-use-case.md`.
- Ringkasan opsional: `PATCH /siswa/{id}/izin-analitik`; `POST /analitik-beasiswa/pola` memilih beasiswa+angkatan; `PATCH /analitik-beasiswa/pola/{id}/publikasi`. Minimal lima alumni dengan izin agregasi. Angka rapor dibulatkan, kelompok ekonomi kecil disembunyikan; tidak ada daftar identitas di DTO. Semester adalah waktu penerimaan pertama yang **tercatat**, bukan kepastian mulai. Pencabutan izin menyembunyikan snapshot terkait. Siswa mengakses `/pola-alumni` saja.
- `GET /beranda` mengembalikan agregat terkini bagi admin atau ringkasan pribadi bagi siswa. `GET /faq` publik.

## Aktivasi yang membutuhkan layanan sekolah

1. **Akun admin:** database lokal belum memiliki pengguna saat preflight. Sediakan email milik sekolah yang sudah diverifikasi. Perintah berikut meminta password secara tersembunyi, tanpa password default:

   ```powershell
   .\php-project.ps1 artisan eightracer:admin email-milik-sekolah --name="Nama Admin" --verify-email
   ```

   Jangan memakai `--verify-email` sebelum kepemilikan email diperiksa. Untuk melengkapi admin lama gunakan `--existing-id=ID`; perintah tidak membuat admin kedua.

2. **Email OTP:** isi SMTP/pengirim yang benar di `.env`, lalu `EMAIL_OTP_ENABLED=true`. Jalankan worker antrean `artisan queue:work --queue=mail,default`. Mailer log/array tidak dapat mengaktifkan OTP. Uji pengiriman ke email milik sendiri sebelum dipakai siswa.
3. **Google:** buat OAuth Web Client untuk origin FE yang sebenarnya; isi `GOOGLE_CLIENT_ID`. Ini berbeda dari `GEMINI_API_KEY`. FE memakai client ID/nonce backend. Pengujian token memakai kunci sintetis; login Google sungguhan perlu konfigurasi origin/client tersebut.
4. **Gemini:** gunakan `GEMINI_API_KEY` di `.env` saja. Jangan pindahkan ke Vite/FE, request contoh, atau Git. Kuota/akses model mengikuti akun provider.
5. **Upload:** ClamAV daemon dan signature terbaru harus tersedia. Jika pemindaian tidak tersedia, upload ditolak. Import XLSX penuh perlu dites pada runtime/server target karena pembacaan file native PHP di lingkungan kerja terkelola tidak normal.
6. **Deploy:** hosting/domain, HTTPS, CORS origin yang tepat, user database terbatas, firewall database, queue worker, backup/restore, dan SMTP masih perlu disiapkan pada server tujuan. Jalankan `artisan security:check`; ini pemeriksaan konfigurasi, bukan sertifikat bahwa seluruh sistem aman.

Frontend belum disambungkan pada pekerjaan ini. Tidak ada UI baru, publikasi deployment, akun/email palsu, atau commit berisi secret yang dibuat oleh implementasi backend ini.

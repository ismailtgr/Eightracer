# Hasil implementasi backend Eightracer

Tanggal: 2 November 2026. Perubahan sudah dipasang langsung di `C:\eightracer\eightracer`. Pekerjaan mengikuti peran backend/database; UI tim frontend belum diintegrasikan.

## Sudah dikerjakan

| Modul | Hasil backend |
|---|---|
| Login dan akun | Login email, Google dengan verifikasi token server, pendaftaran siswa berdasarkan master NISN+email dan OTP, reset password OTP, profil pribadi, foto, perubahan email/password, sesi admin tunggal maksimal 72 jam. |
| Admin sekolah | Perintah provisioning satu admin, email harus sudah diverifikasi pengelola, password diminta secara tersembunyi; tidak ada akun atau password bawaan. |
| Data murid | Master tanpa harus membuat akun, filter/list/detail, keluarga dan ekonomi, wali bersyarat, bantuan sosial, saudara, minat, histori kelas dan referensi. |
| Asesmen | Riwayat kepribadian, gaya belajar, IQ, RIASEC dan MI; koreksi mempertahankan data lama; input parsial/backdate tidak membuang asesmen relevan terbaru. |
| Eligibilitas | Nilai per semester, presensi dihitung server, verifikasi prestasi, bobot per tingkat, syarat bantuan sosial, program berversi, hasil historis dan ringkasan pribadi siswa. Data kurang tidak dianggap nol. |
| Rekomendasi jurusan | Bobot komponen/mapel ditentukan admin, perhitungan transparan, draft dan review, sumber berubah ditolak saat publikasi. |
| Rekomendasi beasiswa | Gemini menjelaskan hasil perhitungan backend, katalog SMA/kuliah/durasi/periode, persiapan jenjang berikutnya, progres kuliah/IPK, snapshot dan persetujuan admin. Tidak melakukan fine-tuning. |
| Alumni | Penerimaan PT, progres per periode, revisi tanpa menimpa histori, UKT/IPI, beasiswa, izin identitas dan nominal terpisah, timeline dari tanggal sebenarnya. |
| Analitik | Ringkasan admin, filter dan ekspor CSV, snapshot pola angkatan berizin minimal lima alumni, pencabutan izin menyembunyikan snapshot terkait. |
| Beranda dan FAQ | Agregat terkini sesuai role dan FAQ publik. |

## Bukti pemeriksaan

- Tes otomatis setelah perbaikan use case: **79 lolos, 0 gagal, 1 dilewati**, dari 80 tes; 593 assertions. Database tes selalu SQLite dalam memori.
- Satu tes import XLSX penuh dilewati karena native PHP `is_readable` tidak bekerja normal dalam lingkungan terkelola. Parser/guard impor tetap mempunyai tes lain; uji unggah penuh harus diulang pada runtime/server tujuan.
- Pemeriksaan sintaks: 187 file PHP, tanpa kegagalan; file yang diedit terakhir diperiksa ulang.
- Migrasi `2026_11_02_000003_complete_prd_backend` berhasil pada MySQL utama, batch 4. Perbedaan default TIMESTAMP MariaDB sudah diperbaiki dengan DATETIME eksplisit untuk OTP.
- Isi 52 tabel lama selain tabel pencatatan migrasi dibandingkan dengan backup dan tetap utuh. Backup terenkripsi sebelum migrasi sudah diverifikasi dapat didekripsi kembali.
- 13 pemeriksaan backend langsung pada MySQL menggunakan data sintetis berhasil. Data uji dibatalkan; tidak ada akun siswa/admin palsu yang tersisa.
- Pengujian Gemini sungguhan: respons valid = true, satu kandidat sintetis, tanpa data siswa dan tanpa penulisan rekomendasi ke database.
- Endpoint lokal `GET /api/faq` merespons 200. Jumlah deklarasi route API: 101.
- Pemindaian source untuk nilai secret aktual dari `.env`: tidak menemukan kecocokan. Pemeriksaan ini mencakup source yang dibaca, **bukan audit ulang seluruh riwayat Git**. Tidak ada commit/push baru dalam pekerjaan ini.

## Belum aktif dan perlu disiapkan

1. Database lokal belum memiliki akun pengguna setelah data uji dibatalkan. Sediakan email admin milik sekolah; gunakan perintah provisioning pada panduan, tanpa membagikan password di chat.
2. SMTP/email pengirim belum tersedia. OTP sengaja nonaktif sampai SMTP teruji dan worker antrean mail berjalan. Ini berlaku untuk pendaftaran manual, perubahan email dan pemulihan.
3. Google Client ID dan origin FE belum dikonfigurasi. Pengujian token Google otomatis memakai kunci sintetis; login Google sungguhan perlu konfigurasi akun sekolah.
4. Frontend milik rekan belum disambungkan ke API. Gunakan kontrak dan contoh request, lalu lakukan uji bersama per role dan alur bisnis.
5. Antivirus daemon/signature belum aktif. Unggahan ditolak bila scan tidak bisa memastikan file bersih.
6. Hosting/domain belum ada. HTTPS, CORS produksi, user database terbatas, password/firewall database, queue worker, backup/restore dan konfigurasi produksi perlu dipasang pada server tujuan. Pemeriksaan `security:check` saat ini masih gagal pada konfigurasi lokal tersebut; proyek **belum dinyatakan siap deployment produksi**.
7. Sekolah perlu menetapkan bobot/ambang program dan jurusan, melengkapi katalog, serta mencatat persetujuan publikasi. Tidak ada bobot atau data sekolah yang dibuat seolah sudah disetujui.

## Berkas untuk tim

- `Panduan_Backend_PRD.md`: kontrak seluruh modul dan petunjuk aktivasi.
- `Contoh_API_PRD.http`: request contoh dengan placeholder; jangan commit kredensial nyata.
- `Daftar_Endpoint.json`: route dari aplikasi yang sudah dipasang.
- `Hasil_Tes.txt`, `Pemeriksaan_MySQL.json`, `Pemeriksaan_Gemini.json`: bukti validasi.
- `changed-files.json` dan `backend-prd.patch`: perubahan source terhadap backup awal pekerjaan. Patch tidak menyertakan `.env`, database/dump, vendor, runtime portable atau backup privat.


## Perbaikan empat kegagalan audit use case — 2 November 2026

Pencarian/filter jenjang katalog sudah bekerja dan bertahan saat pagination. Beasiswa Keduanya ikut filter SMA/Kuliah. Referensi prestasi muncul di jejak alumni siswa dengan proyeksi publik yang tidak memuat lampiran privat. Tren analitik menyediakan status beasiswa per periode, memakai revisi current dan alumni unik; filter beasiswa konsisten.

Empat kegagalan awal lolos; seluruh 13 kasus penerimaan dan regresi keseluruhan lulus selain satu skip impor XLSX yang sudah ada. Dokumentasi kontrak baru ada di `perbaikan-use-case.md`. Tidak ada perubahan schema/migrasi, .env, data sekolah atau UI pada perbaikan ini.

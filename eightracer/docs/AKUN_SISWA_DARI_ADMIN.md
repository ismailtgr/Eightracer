# Akun siswa disiapkan sekolah

Revisi 4 November 2026 menggantikan alur pendaftaran siswa dalam PRD sebelumnya. Tidak ada registrasi publik, termasuk pembuatan akun baru melalui Google.

## Alur operasional

1. Admin mengimpor master siswa melalui mekanisme impor yang sudah tersedia.
2. Admin memilih ID siswa lalu meminta pratinjau pembuatan akun.
3. BE menahan baris tanpa NISN 10 digit yang unik, identitas bertentangan, atau akun yang sudah ada. Data yang belum ada tidak dikarang.
4. Admin menjalankan pembuatan akun. Username adalah NISN; setiap password awal acak dan berbeda. Password hanya dikembalikan sekali kepada admin berwenang dan langsung disimpan sebagai hash di database.
5. Sekolah menyampaikan kredensial kepada siswa secara pribadi. Jangan menyimpan respons berisi password di Git, koleksi Postman, grup kelas, atau log aplikasi.
6. Siswa login dengan NISN dan password awal. Siswa dapat mengganti password sendiri memakai password lama. Setelah berubah, semua token akun dicabut dan siswa login ulang.

Pembuatan akun tidak otomatis memverifikasi kepemilikan email. Akun siswa yang disiapkan sekolah aktif berdasarkan relasi master siswa dan jejak penyediaan oleh admin. Email boleh kosong; tidak dibuat email pengganti. Akun lama tetap tersedia dan tidak direset oleh impor/provisioning ulang.

## Kontrak API

Semua path relatif terhadap /api. Pembuatan akun hanya untuk role administrator, bukan siswa atau Guru BK biasa.

POST /akun-siswa/provision

```json
{"siswa_ids":[101,102],"dry_run":true}
```

Untuk pelaksanaan, kirim dry_run=false dengan ID yang telah diperiksa. Maksimal 100 ID unik per permintaan; dua permintaan per menit. Pratinjau mengembalikan 200 dan status siap/ditahan, tanpa password dan tanpa membuat akun. Pelaksanaan mengembalikan 201 bila ada akun dibuat; ulangi permintaan tidak mengganti password akun yang sudah ada.

Respons pelaksanaan berisi dry_run, created_count, ready_count, held_count, dan data. Setiap baris berisi siswa_id serta status. Baris dibuat juga berisi user_id, username dan initial_password (rahasia sekali tampil); baris ditahan berisi alasan. Respons memakai Cache-Control: no-store, private.

POST /login

```json
{"nisn":"0012345678","password":"<password pribadi>"}
```

GET /me dan respons login menambahkan account_active, school_provisioned, password_change_recommended. email dapat null; email_verified tetap false bila belum diverifikasi. Tidak ada kewajiban verifikasi email untuk siswa yang telah disiapkan admin dari master siswa. Admin/Guru tetap harus memenuhi aturan email dan akses staf.

PUT /profile/password

```json
{"current_password":"<password lama>","password":"<password baru>","password_confirmation":"<password baru>"}
```

Password minimal 12 karakter dengan huruf besar/kecil, angka, simbol; maksimal 72 byte. Endpoint mengubah akun pemilik token saja, memvalidasi password lama, dan mencabut seluruh sesi. Penggantian pertama dianjurkan (password_change_recommended); tidak dipaksa melalui layar baru.

POST /register dan /register/verify sekarang selalu 410. Challenge lama tidak dapat dipakai untuk membuat akun. POST /auth/google hanya dapat masuk/menghubungkan akun yang sudah ada sesuai bukti kepemilikan email; pengguna Google baru tidak dapat mengklaim master siswa atau membuat akun.

Pemulihan melalui email OTP hanya tersedia bagi email terverifikasi dengan layanan email aktif. Siswa tanpa email meminta bantuan admin; tidak ada bypass OTP publik.

## Masa login dan FE

Sesi admin/staf maksimal 72 jam (3 hari), dihitung sejak diterbitkan, tidak diperpanjang oleh aktivitas. Login admin baru mencabut sesi admin yang lama. Akun admin yang dipakai bergantian dapat keluar sebelum 72 jam. Siswa maksimal 168 jam (7 hari), mendukung beberapa perangkat. Ganti password dan logout juga mencabut token sesuai cakupannya.

FE menyimpan cookie HttpOnly dengan expires_at dari BE; jangan memasang batas terpisah dua hari atau menganggap gangguan jaringan sebagai token kedaluwarsa. Bedakan 401 (sesi tidak sah), 403 (izin), dan 503 (layanan belum dapat dihubungi). localhost dan 127.0.0.1 mempunyai cookie masing-masing: gunakan alamat FE yang sama.

Tampilan katalog dikembalikan ke komponen pada branch FE b4. Pekerjaan BE menyiapkan data, aturan dan kontrak; tidak menambah halaman riwayat, kartu ringkasan, atau kolom baru. Catatan KIP sekolah tetap merupakan data sumber, bukan otomatis katalog program/keputusan penerima beasiswa.

# Integrasi dan uji FE B4 — 5 November 2026

Paket ini berasal dari kode dan respons API proyek utama `C:\eightracer\eightracer`, diperiksa 4 November 2026. Tampilan FE tidak diubah.

## Lingkungan dan alamat

| Lingkungan | Base URL | Database | Penggunaan |
|---|---|---|---|
| Utama, laptop BE | `http://127.0.0.1:8000/api` | `eightracer_db` | Data sekolah; jangan untuk pengujian tulis |
| Uji, laptop BE | `http://127.0.0.1:8001/api` | `eightracer_b4_uji_20261105` | Data sintetis, boleh untuk integrasi |
| Uji, laptop FE melalui Tailscale | `https://laptop-3qspir74.tail5f273f.ts.net/b4-test/api` | Database uji yang sama | **Gunakan hanya setelah health check di bawah berhasil** |

URL utama Tailscale tanpa `/b4-test` masih mengarah ke lingkungan utama. Jangan memasukkan akun uji baru ke URL utama.

API lokal dan jalur HTTPS uji sudah berjalan. URL HTTPS utama merespons 401 tanpa token; `/b4-test/up` sudah merespons 200 dengan penanda database uji setelah aktivasi helper. Login dan operasi katalog melalui HTTPS sudah diuji dari laptop BE. Status terbaru dicatat di `STATUS.md`. Akses dari laptop FE tetap perlu diuji sendiri.

Health check: buka `https://laptop-3qspir74.tail5f273f.ts.net/b4-test/up`. Wajib HTTP 200 dan header `X-Eightracer-Environment: b4-test-20261105`. Header ini membantu membedakan lingkungan; bukan mekanisme autentikasi.

52 migrasi sudah dijalankan pada database uji. Aplikasi uji memakai akun SQL khusus dengan SELECT/INSERT/UPDATE/DELETE hanya pada database uji. Tidak ada salinan data pribadi sekolah di database uji. Basis utama berisi 872 siswa dan 599 rekam penerimaan alumni saat diperiksa.

## Akun dan data yang boleh diuji

Kedua akun baru sudah aktif dan `email_verified=true`:

| Akun | Login | User ID | Profil siswa |
|---|---|---:|---:|
| Admin uji | `admin.b4.uji@eightracer.test` | 900001 | — |
| Siswa uji | `siswa.b4.uji@eightracer.test` atau NISN `9999900001` | 900002 | 900001 |

Password berada di berkas pribadi pemilik BE: `C:\eightracer\.local\b4-test\AKUN_PRIBADI.txt`. Berkas tersebut diabaikan Git dan tidak masuk paket ini. Pemilik mengirimkannya secara pribadi kepada penguji; jangan kirim ke grup atau mengekspor environment Postman yang sudah diisi.

| Data sintetis | ID | Boleh dilakukan |
|---|---:|---|
| Profil siswa akun uji | `siswa_id=900001` | Baca; siswa mengusulkan perubahan profil, admin meninjau |
| Alumni sintetis | `siswa_id=900002` | Tambah/koreksi progres dengan akun admin |
| Siswa pembanding | `siswa_id=900003` | Uji penolakan akses dari akun siswa |
| Rekam penerimaan alumni | `study_tracer_id=900001` | Dipakai bersama alumni 900002 |
| Progres alumni | `alumni_progress_id=900001` | UKT 1.500.000, IPK 3,65; koreksi membuat revisi baru |
| Kampus sintetis | `perguruan_tinggi_id=900001` | Filter dashboard |
| Beasiswa contoh | `beasiswa_id=900001` | Baca/edit/arsip/aktifkan; namanya selalu berawalan UJI |
| Program eligibilitas contoh | `program_eligibilitas_id=900001` | Baca/pakai untuk kriteria status |

Semua data pada DB uji boleh dipakai untuk percobaan dengan awalan **UJI**. Pertahankan akun, hubungan siswa akun uji, dan ID fixture di atas agar skenario bersama tetap berjalan. Untuk CRUD berulang, buat beasiswa baru melalui POST dan simpan ID respons. Verifikasi awal meninggalkan beberapa beasiswa UJI dalam keadaan arsip; ini disengaja.

Jangan menjalankan seed/reset/migrasi penghapus pada `eightracer_db`. Seeder `B4IntegrationTestSeeder` menolak database selain nama uji persis di atas dan menolak database berisi akun/siswa; bukan tombol reset harian.

## Memasang alamat di FE

Pada laptop FE yang memakai Next.js dan route server/BFF seperti checkout lokal sekarang, isi `.env.local`:

```dotenv
API_BASE_URL=https://laptop-3qspir74.tail5f273f.ts.net/b4-test/api
```

Jika kode FE masih membaca `NEXT_PUBLIC_API_BASE_URL`, samakan nilainya; hapus nilai lama yang menimpa. Tidak ada password/API key di nilai base URL. Restart dev server FE setelah perubahan. Laptop FE harus tersambung ke tailnet dan diizinkan mengakses laptop BE melalui HTTPS 443. Jangan membuka port MySQL ke FE.

`localhost` di laptop FE menunjuk laptop FE sendiri. Alamat 8001 hanya berlaku di laptop BE. Pengujian UI di laptop BE boleh memakai `API_BASE_URL=http://127.0.0.1:8001/api`, tetapi konfigurasi FE utama sengaja belum diganti otomatis.

Checkout FE lokal saat ini baru menyediakan `GET /api/b4/beasiswa`; route BFF detail/POST/PUT/DELETE belum tersedia. FE perlu menghubungkan tindakan tambah/edit/arsip/aktifkan ke kontrak BE di `KONTRAK_KATALOG.md`. Ini terpisah dari pemasangan endpoint BE; desain halaman yang ada tetap digunakan. Browser FE dapat tetap memakai BFF dan cookie HttpOnly; jangan menaruh bearer token di localStorage. Login langsung di Postman memakai bearer sesuai kontrak BE.

## Postman dan contoh respons

1. Import `Eightracer_B4.postman_collection.json` dan environment `B4_Tailscale.postman_environment.json` (FE) atau `B4_Local.postman_environment.json` (BE).
2. Pilih environment. Isi password hanya pada nilai lokal/pribadi. Token awal kosong; login menyimpannya saat dijalankan.
3. Jalankan folder **Admin** berurutan, lalu folder **Siswa**. Ada 24 request. Arsip/aktifkan bekerja pada ID yang baru dibuat, bukan menghapus fixture.
4. Skrip sebelum request membatasi base URL dan memeriksa header lingkungan uji. Hentikan jika penanda tidak cocok. Pakai Postman Desktop yang mendukung `pm.execution.skipRequest`; jangan menghapus penjagaan agar request bisa dipaksa jalan.
5. Logout di akhir. Jangan mengekspor token/password setelah login. File yang dibagikan di paket ini semuanya memiliki nilai secret kosong.

`Contoh_Payload_Beasiswa.json` adalah body POST/PUT yang valid. `Contoh_Response_Terverifikasi.json` berisi respons nyata dari database sintetis, tanpa respons login/token/password. ID hasil POST dan waktu dapat berbeda setiap pengujian. URL paginator contoh berasal dari pengujian lokal; FE sebaiknya memakai nomor `current_page/last_page`, bukan mengikuti URL absolut tersebut melalui BFF.

## Cakupan endpoint pendukung

Semua path berikut relatif terhadap base URL `/api`:

| Method/path | Akses | Body/filter penting | Respons |
|---|---|---|---|
| POST `/login` | Tanpa token | `{email,password}` **atau** `{nisn,password}`; jangan keduanya, jangan kirim role | 200 `{message,user,token,expires_at}` |
| GET `/me` | Kedua akun | Bearer | 200 objek user, termasuk `role` dan `email_verified` |
| POST `/logout` | Kedua akun | `{}` | 200; token dicabut |
| GET `/profil-siswa/saya` | Siswa | Tidak menerima siswa_id lain | 200 `{data:{id,nisn,nama_lengkap,...}}` |
| POST `/profil-siswa/saya/submission` | Siswa | Misalnya `{nama_panggilan:"UJI Siswa"}` | 201 `{data,message}`, menunggu review |
| GET `/rekomendasi-beasiswa/saya` | Siswa | Hanya hasil yang sudah disetujui admin | 200 paginator; awalnya kosong |
| GET `/siswa/900001/rekomendasi-beasiswa/preview` | Admin/BK | `target_jenjang=SMA` | 200 `{data:{target_jenjang,pemeringkatan_ml,kandidat}}` |
| POST `/siswa/900001/rekomendasi-beasiswa/generate` | Admin/BK | `{target_jenjang:"SMA"}` | 201 jika Gemini siap; **503 di lingkungan uji tanpa key** |
| POST `/chat-beasiswa` | Siswa | `{message:"Apakah saya memenuhi syarat?",beasiswa_id:900001,target_jenjang:"SMA"}` | 200 `{data:{answer,status,llm_used,eligibility,...}}` |
| GET `/alumni/900002/progress` | Admin | Tanpa body | 200 `{data:[...]}` |
| POST `/alumni/900002/progress` | Admin | Contoh di bawah | 201 `{data:{...}}` |
| PATCH `/alumni-progress/{id}` | Admin | Body progres lengkap, periode yang sama | 201, membuat revisi baru; 409 jika sudah dikoreksi |
| GET `/dashboard/ukt-ipk` | Admin/BK | `ukt_lt=2000000`; atau `ukt_min`, `ukt_max`, `ipk_min`, `ipk_max`, `tahun_lulus`, `perguruan_tinggi_id`, `program_studi`, `jalur_masuk`, `periode`, `beasiswa_id`, `status_beasiswa`, `penerima_kipk` | 200 `{data:{jumlah_alumni,ukt,ipk,per_kampus_prodi,tren_ipk,...}}` |

Contoh **tambah** progres baru; ganti periode bila sudah digunakan:

```json
{
  "study_tracer_id": 900001,
  "periode": "UJI FE Periode 2",
  "semester": 4,
  "tahun_lulus": "2025",
  "status_pendidikan": "aktif",
  "ipk_terkini": 3.7,
  "nominal_ukt": 1600000,
  "tanggal_update": "2026-11-04",
  "beasiswa": []
}
```

Tanggal update tidak boleh masa depan. Semester 1–40, IPK 0–4, UKT 0–9.999.999.999.999. `nominal_ipi` hanya untuk jalur Mandiri. Status pendidikan `aktif|cuti|lulus|nonaktif`. Satu periode tidak boleh ditambahkan dua kali; koreksi memakai PATCH dan ID progres, bukan ID siswa. Jangan mengirim `is_published`, `publish_financials`, atau `siswa_id` pada payload progres. Publikasi merupakan tindakan terpisah.

## AI, data kosong, dan batas uji

Environment uji memiliki key Gemini kosong, terpisah dari key utama. Preview kriteria tetap bekerja; chat memakai jawaban faktual dengan `llm_used=false`. Generate melalui Gemini mengembalikan 503 dan tidak membuat rekomendasi palsu. Ini **belum** membuktikan pengujian Gemini secara end-to-end. Jangan menampilkan hasil sintetis seolah bukti penerimaan beasiswa nyata.

Katalog sintetis kecil dan minat belum lengkap, sehingga pemeringkatan TF-IDF dapat menggunakan fallback kriteria. `belum_diketahui`, hasil rekomendasi kosong, dan 503 merupakan state yang harus ditangani FE, bukan diganti dengan klaim lolos. Pengujian impor Excel dengan scanner aktif dan pengiriman OTP email sungguhan belum tercakup pada uji langsung ini.

## Menjalankan layanan pada hari uji

Pemilik BE menjalankan `C:\eightracer\Start_B4_Uji.ps1`. Untuk mengaktifkan jalur Tailscale dan mempertahankan jendela proses, jalankan `C:\eightracer\Aktifkan_B4_Uji.cmd` dengan klik kanan → Run as administrator, lalu biarkan jendelanya terbuka selama uji. Helper hanya menambahkan `/b4-test` ke Tailscale Serve; tidak membuka Funnel/publik atau port database. Jika path sudah dipakai target lain, helper berhenti. Pemeriksaan HTTP memakai `-UseBasicParsing`. Penantian proses tidak mematikan pengaturan sleep Windows.

Sebelum mulai: MySQL 3306 berjalan, Tailscale tersambung pada kedua laptop, API 8001 aktif, health check sesuai penanda, lalu coba login dari laptop FE. Laptop BE perlu menyala, internet aktif, terhubung charger, dan tidak sleep selama pengujian. Proses API dev bukan Windows service dan perlu dijalankan lagi jika terminal/prosesnya berhenti atau laptop restart. Tidak ada jaminan otomatis bahwa laptop tetap online pada 5 November; sepakati jam uji dengan pemilik laptop.

Panduan tooling: [Tailscale Serve](https://tailscale.com/docs/reference/tailscale-cli/serve), [penjagaan request Postman](https://learning.postman.com/latest-v-12/docs/tests-and-scripts/write-scripts/postman-sandbox-reference/pm-execution). Kontrak aplikasi diambil langsung dari controller, validator, evaluator, dan pengujian proyek ini.

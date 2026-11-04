# Tahap pertama data asli B4: alumni

Pemasangan 4 November 2026 di proyek utama C:\eightracer. Halaman yang dipakai tetap /admin/alumni-perguruan-tinggi dan /admin/alumni-perguruan-tinggi/informasi-detail milik FE. Tidak ada halaman, kolom, tema, atau kartu ringkasan baru.

## Cakupan data

Database utama eightracer_db mempunyai 599 catatan penerimaan berstatus diterima, terkait 576 alumni unik dan 49 kampus. Angka ini berbeda dari jumlah seluruh profil siswa/alumni (872) maupun seluruh referensi kampus (58). Satu alumni bisa memiliki lebih dari satu penerimaan; tidak dipilihkan satu kampus seolah pasti tempat kuliah terkini.

Tidak dilakukan impor ulang, penggandaan, penghapusan atau perubahan data sekolah. Riwayat yang belum cukup untuk dibentuk menjadi penerimaan formal tetap pada arsip sumber/profil untuk dilengkapi.

Tabel dan detail sekarang membaca nama, kampus, jenis kampus, prodi, jalur, tahun lulus dan kelas jika tersedia dari relasi yang sama. Status terakhir berasal dari progres perkuliahan pada penerimaan tersebut. Tidak ada progres berarti belum tersedia; tidak dianggap aktif kuliah, penerima KIP-K, semester 4, atau mempunyai biaya tertentu.

Saat pemasangan, alumni_progress kosong. Form, simpan, impor, edit dan hapus progres di halaman detail ditahan; tidak lagi menulis progres contoh ke localStorage. Data IPK/UKT/IPI yang belum ada tidak diisi nol. Nilai nol hanya ditampilkan jika memang tercatat di BE.

## Kontrak API BE

Seluruh path relatif terhadap /api. Wajib token admin/Guru BK yang sah. Tanpa token 401; siswa 403.

| Method | Path | Hasil |
|---|---|---|
| GET | /alumni | Paginator penerimaan diterima |
| GET | /alumni/{id} | Detail satu penerimaan dan progres yang terhubung |

ID detail adalah study_tracer.id, bukan siswa.id. Gunakan id yang dikembalikan baris daftar; siswa_id hanya menjelaskan profil pemiliknya. Progres pada kampus/penerimaan lain tidak dicampur.

Filter daftar: search (nama, maks100), perguruan_tinggi_id, program_studi (maks255), jalur_masuk (maks50), tahun_lulus (4 digit), page (integer >=1), per_page (1–100, default8), include_filters (boolean).

include_filters=1 menambahkan filters.universities [{id,nama_pt}], filters.study_programs dan filters.admission_paths. Pilihan filter berasal dari penerimaan sekolah yang tersedia, bukan nama kampus/prodi contoh.

Respons daftar memuat id, siswa_id, nama_lengkap, tahun_lulus, perguruan_tinggi {id,nama_pt,jenis_pt}, program_studi, jalur_masuk, status_penerimaan, status_terakhir. Detail menambahkan has_nisn (boolean, tanpa NISN mentah), kelas_terakhir, strata_pendidikan, sub_jalur_mandiri, pilihan_ke, tahun_masuk_pt, nilai_rapor_pendaftaran, skor_utbk, dan progress.

Nilai yang tidak diketahui tetap null atau array kosong. Catatan mentah sekolah, alamat, email, WA, relasi akun, token, dan password tidak diproyeksikan oleh endpoint alumni ini. API tidak mengubah izin publikasi alumni untuk siswa.

## Sambungan FE yang dipakai

Browser meminta GET /api/b4/alumni dan GET /api/b4/alumni/{id}. Server FE meneruskan cookie HttpOnly sebagai bearer ke BE; browser tidak menerima token. URL dasar mengikuti API_BASE_URL yang sudah terpasang (database utama, bukan /b4-test). Paginasi tetap delapan baris mengikuti tabel FE semula.

Parser memproyeksikan respons BE ke field tampilan serta menolak respons rusak. Pencarian/filter gagal atau koneksi putus menampilkan error dan tombol coba lagi; tidak beralih ke data contoh. Data hanya muncul untuk staf yang terautentikasi. Tampilan alumni siswa, timeline, katalog beasiswa dan rekomendasi belum diubah dalam tahap ini.

## Pemeriksaan

- BE: 149 tes, 1074 assertions, tidak ada kegagalan; satu tes XLSX lama dilewati.
- FE: lima tes parser alumni lulus; pemeriksaan TypeScript dan ESLint pada berkas yang diubah lulus.
- 33 pemeriksaan HTTP pada FE dan BE utama berhasil: autentikasi, hitungan nyata, pilihan kampus, pencarian, kombinasi filter, paginasi, detail dengan ID yang sama, data kosong, penolakan query/ID salah, privasi, serta pembatasan staf.
- Halaman daftar dan detail memberikan HTTP200. Tidak ada klaim pemeriksaan visual lewat browser yang berhasil; perubahan memakai struktur dan CSS FE yang ada.
- Tidak memerlukan migrasi baru untuk tahap alumni ini.

# Impor massal sekolah: CSV/XLSX dan banyak tab

Gunakan endpoint staff `/api/imports` untuk pratinjau, lalu `/api/imports/{id}/commit` dan pantau GET batch. Parser tidak mengambil spreadsheet dari URL, tidak menjalankan makro/formula, dan tidak menyamakan murid berdasarkan nama saja. Export Google Sheets/Forms menjadi XLSX/CSV UTF-8 dahulu; sumber sekolah tetap perlu dicocokkan dengan field yang dimiliki aplikasi.

## Proses yang disarankan

1. Unduh template `/api/imports/templates/{module}` dan referensi ID dari backend. Pastikan NISN berupa teks 10 digit yang asli; jangan menambah nol untuk memperbaiki nilai yang tidak diketahui. Alumni tanpa NISN boleh dirujuk dengan siswa_id existing setelah pemetaan terverifikasi.
2. Pakai `source` stabil, misalnya school-forms-ukt-2026 (huruf/angka/_.:-), dan external_id sumber yang tidak berubah setelah sorting. Nilai kosong tidak dibuat nol. Pisahkan pembuatan master siswa baru dari impor nilai/progres yang merujuknya: target harus sudah ada saat preview.
3. Pilih insert_only agar record existing ditandai perlu tinjau; upsert hanya untuk memperbarui identitas/natural key yang sudah cocok. Preview tidak mengubah tabel domain sekolah, tetapi menyimpan rencana terenkripsi dan ringkasan audit.
4. Untuk banyak tab, petakan setiap tab atau abaikan secara eksplisit. Ulas summary per tab dan seluruh error; satu baris invalid menggagalkan seluruh batch. Sampel hanya 3 baris, bukan pengganti pemeriksaan sumber.
5. Commit memakai digest hasil preview, sebelum 24 jam. Worker memvalidasi ulang role, identitas, digest dan fingerprint target di dalam transaksi. Bila master berubah sejak preview, batch gagal; preview ulang terhadap keadaan terbaru.
6. Completed berarti semua baris berhasil. Failed berarti rollback domain dengan processed=0. Unggah file dan opsi yang sama mengembalikan batch aktif/completed agar tidak menggandakan impor; file atau external_id yang berbeda tetap memerlukan peninjauan sumber.

## Contoh mapping XLSX

Multipart `file`: berkas XLSX; source: school-2026; mode: insert_only; number_format: id. Field sheets berisi JSON:

```json
[
  {"sheet":"Nilai Semester 1","module":"grades","header_row":2,"columns":{"nisn":"NISN","semester":"SEMESTER","mata_pelajaran_id":"ID MAPEL","nilai_akhir":"NILAI"}},
  {"sheet":"Respons UKT","module":"financial","header_row":1,"columns":{"external_id":"ID RESPONS","siswa_id":"ID ALUMNI","study_tracer_id":"ID PENERIMAAN","periode":"PERIODE","semester":"SEMESTER","status_pendidikan":"STATUS KULIAH","tanggal_update":"TANGGAL","nominal_ukt":"UKT","ipk_terkini":"IPK"}}
]
```

Field ignore_sheets misalnya `["Petunjuk"]`. Header mapping adalah **field canonical -> judul kolom sumber**, bukan sebaliknya. Header baris 1–10. Kolom yang tidak dipetakan diabaikan secara sadar; untuk template canonical tanpa mapping, header yang tidak didukung ditolak. Pemetaan tidak menerjemahkan label jalur/status/ID secara otomatis. Jika file asli sekolah hanya menyebut nama/universitas tanpa ID yang jelas, lakukan rekonsiliasi dahulu, jangan coba menebak kecocokan.

CSV memakai header pada baris pertama; delimiter default comma, bisa semicolon/tab. `number_format=canonical` memakai titik desimal tanpa pemisah ribuan; `id` mendukung contoh Rp 1.500.000 dan IPK 3,75. Jangan mencampur konvensi angka dalam satu batch. Untuk tanggal CSV gunakan YYYY-MM-DD; XLSX dapat memakai serial tanggal Excel sesuai kalender workbook (1900/1904). Tanggal/semester/tahun tidak diisi otomatis bila sumber tidak memuatnya.

## Batas pemrosesan dan keamanan

- CSV/XLSX maksimal 5 MiB, 2.000 baris data total, 50 tab, 52 kolom, 50.000 sel; rencana JSON maksimal 5 MiB.
- ClamAV wajib tersedia dan lolos pemindaian, baik CSV maupun XLSX. Pemindai gagal berarti unggahan ditolak. Tes memalsukan scanner secara terisolasi, bukan menonaktifkan antivirus di aplikasi.
- Arsip XLSX dibatasi ukuran terurai, jumlah entri dan rasio kompresi oleh guard existing; makro, formula, external relationship, DTD/entity dan XML rusak ditolak. Sel tidak dieksekusi.
- Semua tab, termasuk yang diabaikan, tetap melalui pemeriksaan keamanan workbook. Tab diabaikan tidak masuk domain. Reader bukan kalkulator Excel: ganti formula menjadi nilai resmi sebelum unggah.
- Hanya field allowlist dipakai; tidak menerima role/user_id/password/izin publikasi. Pemetaan source+external_id tidak boleh berpindah murid/mapel/periode/penerimaan. Nama saja tidak menggabungkan identitas.
- Plan terenkripsi, hanya pemilik batch dapat melihat/mengommit; isi dihapus setelah sukses atau kedaluwarsa melalui scheduler. Metadata sumber/status/audit tetap tersimpan. Jangan menyimpan berkas atau sampel sekolah ke Git.

## Field tiap modul

### students

Pembuatan baru: NISN asli, nama_lengkap, jenis_kelamin L/P. Target existing bisa memakai siswa_id. Tidak menciptakan akun login. Field kosong saat upsert mempertahankan nilai existing.

`siswa_id, nisn, nis, nama_lengkap, jenis_kelamin, registration_email, tahun_lulus, tanggal_lahir, alamat_rumah, nilai_tka, external_id`

### grades

Identitas + semester 1–5 + ID mapel existing + nilai_akhir 0–100. Natural key siswa/mapel/semester. Semua baris mapel bisa dibawa sekaligus.

`siswa_id, nisn, semester, mata_pelajaran_id, nilai_akhir, external_id`

### assessments

Tanggal asesmen asli wajib. Isi salah satu IQ/tipe/gaya/RIASEC/MI yang benar-benar diketahui. Jika RIASEC disertakan, enam skor harus lengkap. Rentang skor 0–100, IQ 0–300. Tanggal sama harus menunjuk koreksi_dari_id yang sah; insert_only tidak menimpa tes existing. Catatan kualitatif tanpa tanggal tidak dipalsukan menjadi asesmen.

`siswa_id, nisn, tanggal_asesmen, tipe_kepribadian, gaya_belajar, iq, realistic_score, investigative_score, artistic_score, social_score, enterprising_score, conventional_score, mi_linguistik, mi_logika_matematika, mi_visual_spasial, mi_kinestetik, mi_musikal, mi_interpersonal, mi_intrapersonal, mi_naturalis, koreksi_dari_id, external_id`

### alumni

ID kampus, program_studi, strata D3/D4/S1/S2/S3, jalur, status menunggu/diterima/tidak_diterima dan tahun_masuk_pt. SNBP memerlukan rapor pendaftaran, SNBT skor UTBK. Subjalur hanya Mandiri: Rapor, Skor UTBK, Tes Kampus, Konsorsium. Upsert berdasarkan siswa/kampus/prodi/jalur/tahun; publikasi dicabut jika diperbarui.

`siswa_id, nisn, perguruan_tinggi_id, program_studi, strata_pendidikan, jalur_masuk, sub_jalur_mandiri, pilihan_ke, status_penerimaan, nilai_rapor_pendaftaran, skor_utbk, tahun_masuk_pt, tanggal_masuk_pt, is_pelamar_kipk, external_id`

### financial

Identitas, study_tracer_id milik alumni, periode, semester 1–40, status aktif/cuti/lulus/nonaktif dan tanggal_update wajib. Tahun lulus diambil dari master jika tersedia, tidak ditebak. IPK 0–4, UKT/IPI rupiah nonnegatif; IPI hanya Mandiri. beasiswa_id wajib menunjuk katalog, status proses/diterima/ditolak. Tidak ada beasiswa baru yang dibuat dari teks bebas. Satu beasiswa per baris, satu progres per penerimaan/periode per batch; beberapa beasiswa pada progres gunakan endpoint progres existing dengan array. Upsert membuat revisi dan mempertahankan UKT/IPK/beasiswa yang tidak dikirim; tidak otomatis mempublikasikan.

`siswa_id, nisn, study_tracer_id, periode, semester, tahun_lulus, status_pendidikan, ipk_terkini, penerima_kipk, nominal_ukt, nominal_ipi, tanggal_update, beasiswa_id, status_beasiswa, nominal_beasiswa, external_id`

### aid

Identitas + jenis_bantuan_id existing + aktif Ya/Tidak atau 1/0. Nomor dan tanggal opsional; tanggal akhir >= awal. Per siswa/jenis bantuan ada satu status target.

`siswa_id, nisn, jenis_bantuan_id, nomor_penerima, tanggal_mulai, tanggal_selesai, aktif, external_id`

### economy

Identitas + nama/kondisi kedua orang tua. Kondisi hidup/meninggal/bercerai. Penghasilan opsional; 0 berarti diketahui nol, kosong berarti tidak diketahui/dipertahankan. Kondisi non-hidup memerlukan wali yang sudah dicatat pada modul ekonomi. Impor ini tidak mengubah konfirmasi kelengkapan bantuan secara diam-diam.

`siswa_id, nisn, ayah_nama, ayah_kondisi, ayah_penghasilan, ayah_pekerjaan, ibu_nama, ibu_kondisi, ibu_penghasilan, ibu_pekerjaan, external_id`

## Data sekolah sebelumnya

Impor curated penerimaan PT lama tetap tersedia pada /api/alumni/import dengan kontrak khususnya. Jangan menjalankan ulang semua spreadsheet sekolah lewat generic import tanpa rekonsiliasi dan external_id, agar sumber lama tidak terduplikasi. Pada pekerjaan ini tidak ada spreadsheet sekolah baru yang diimpor dan tidak ada data sintetis dimasukkan ke MySQL.

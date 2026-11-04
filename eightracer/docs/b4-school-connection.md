# Sambungan data B4 ke FE branch b4

Kode diterapkan di proyek utama C:\eightracer. Komponen katalog, formulir katalog, analitik, pagination, dan Jejak Alumni memakai komponen dari repository FE Zharussanoy/Eightracer-SMAN-8-MALANG branch b4. Kontrol tambahan di luar komponen tersebut tidak dibuat.

## Katalog

- Browser: GET /api/b4/beasiswa -> BE: GET /api/beasiswa, dengan cookie HttpOnly diteruskan sebagai Bearer oleh server FE.
- Detail: GET /api/b4/beasiswa/{id} -> GET /api/beasiswa/{id}.
- Tambah, ubah, arsip: POST, PUT, DELETE pada path yang sama.
- Aktifkan kembali: ambil detail, lalu PUT dengan is_active=true; semua informasi/kriteria lama dipertahankan.
- Respons BE menyediakan kriteria dan tetap mempertahankan kriteria_kelayakan_beasiswa untuk kompatibilitas.
- is_active / status_program tidak berarti pendaftaran dibuka. status_pendaftaran dihitung dari tanggal lengkap. Periode teks tanpa tanggal/tahun tidak ditebak.
- Katalog berisi program yang benar-benar tersimpan dalam basis_data_beasiswa. Catatan penerima dari sekolah tidak otomatis dijadikan katalog dengan syarat atau tanggal buatan.
- Impor CSV menggunakan kontrol dari FE: pembacaan/pratinjau di browser, penyimpanan per baris melalui API tervalidasi. Kegagalan menghentikan baris selanjutnya; periksa katalog sebelum mencoba lagi.

## Analitik dan PDF

- Browser GET /api/b4/analytics -> BE GET /api/analitik-beasiswa/sekolah.
- Pilihan filter GET /api/b4/analytics/options mencakup program katalog serta indikator/program dari sumber sekolah.
- Filter: angkatan (tahun lulus 4 digit), prodi (teks), beasiswa_id (ID katalog atau kip/program:hash), periode (periode progres kuliah).
- Tombol Ekspor PDF dari FE memakai GET /api/b4/reports/alumni-pdf -> BE GET /api/analitik-beasiswa/sekolah/pdf. Filter dan perhitungan memakai service yang sama dengan tampilan.
- Tidak perlu migration baru untuk sambungan ini. Tabel yang dipakai sudah tersedia.
- Renderer PDF memakai Python/ReportLab melalui B4_PYTHON_BINARY, batas waktu 30 detik, hasil maksimum 10 MB. Kegagalan layanan mengembalikan pesan umum 503.
- Statistik menggunakan alumni unik, tidak menjumlahkan semua penerimaan PT sebagai individu berbeda.
- V pada kolom KIPK dibaca sebagai tanda centang sekolah. KIP/KIP-K di sini adalah informasi yang tercatat di sekolah, bukan bukti keputusan penyelenggara.
- TPKS, BEBAS IPI, dan teks nama kampus tidak dianggap beasiswa bernama. Bebas IPI tidak diubah menjadi UKT nol.
- UKT/IPK menggunakan progres terbaru. Nol yang memang tercatat dibedakan dari null/belum tercatat.
- Catatan spreadsheet tanpa periode beasiswa tidak mengisi tren semester secara perkiraan.
- Filter kampus/prodi hanya memakai catatan yang terhubung ke penerimaan kampus/prodi tersebut.
- Angka bergerak mengikuti perubahan database. Audit sumber sekolah menemukan 697 profil lulusan, 599 penerimaan dari 576 alumni; 120 indikator KIP/KIP-K dan 3 alumni beasiswa lain dari spreadsheet. Progres yang dimasukkan kemudian dapat menambah angka tersebut.

## Siswa dan privasi

- GET /api/b4/student-alumni dan /{id} memakai GET /api/jejak-alumni dan /{id}.
- Tidak ada fallback dari endpoint siswa ke direktori staff.
- Nama, kampus, prodi, jalur, prestasi yang diizinkan, dan timeline mengikuti data asli.
- Publikasi admission, publikasi progres, dan izin nominal tetap terpisah. Menyambungkan FE tidak otomatis mengubah flag persetujuan atau menerbitkan seluruh data sekolah.
- NISN, kontak, penghasilan orang tua, bukti privat, dan referensi persetujuan tidak diteruskan ke DTO siswa.
- Pencarian/filter Jejak Alumni mengikuti komponen FE saat ini: berlaku pada halaman pagination yang sedang dibuka.

## Verifikasi

173 tes backend / 1314 assertions (1 tes XLSX lama dilewati karena prasyarat yang tidak tersedia), 30 tes kontrak/form FE lulus; TypeScript dan ESLint bagian yang disentuh lulus. Pembacaan katalog/detail, analitik, filter tahun, serta PDF diuji melalui sesi admin pada server lokal. Rangkaian auth FE lama memerlukan build production terpisah; tidak dihitung sebagai lulus karena build tersebut tidak tersedia. Pemeriksaan live dilakukan tanpa membangun ulang FE di drive yang terbatas.

Kredensial, snapshot privat, dan hasil audit disimpan di .local yang diabaikan Git. Tidak menambah seed dummy ke database sekolah.

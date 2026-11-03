# Perubahan diagram Eightracer

Revisi 2 November 2026. Seluruh enam diagram disusun ulang menggunakan database MySQL dan kode backend saat diperiksa. Berkas versi lama tetap dipertahankan di folder sebelumnya.

| Bagian | Versi lama | Revisi ini |
|---|---|---|
| ERD awal | 46 tabel, 325 kolom, 50 FK | 58 tabel, 475 kolom, 66 FK aktual |
| Use case | 24 pada paket awal; 25 pada audit antara | 27 dengan ID UC01–UC25 dipertahankan |
| Activity | Alur sebelum pembaruan | Satu alur terpadu: pilihan modul, validasi akses, impor, tinjauan dan publikasi |
| Sequence | Alur sebelum pembaruan | Satu diagram, 91 pesan; alur bersama digabung pada loop/opt/alt |
| Class | Model sebelum pembaruan | 48 model Laravel dan 14 layanan aktual, 151 metode publik |
| Skenario | Terpisah dari sebagian model | 27 alur utama + 27 alternatif, termasuk properti native use case EA |

UC26 menjelaskan rekonsiliasi sumber sekolah oleh backend; UC27 menjelaskan pengelolaan referensi. UC26 tidak berarti endpoint impor umum menerima seluruh format spreadsheet sekolah.

Data prestasi berasal dari sekolah. Label pending berarti belum ditinjau melalui alur aplikasi. Sumber tetap disimpan; kemungkinan pengulangan ditandai untuk konfirmasi, bukan dianggap salah. Baris belum lengkap disimpan dalam catatan sumber sampai kolom wajib dikonfirmasi.

174 catatan peminatan belum dijadikan asesmen formal karena tanggal tes belum diketahui. Label gaya belajar/MI/RIASEC tidak diubah menjadi skor angka buatan. ERD menampilkan tabel asesmen yang memang sudah ada, bukan tabel impor baru rekaan.

Data sekolah terkini pada snapshot: 872 profil siswa/alumni, 599 penerimaan PT, 58 referensi perguruan tinggi, 17 prestasi terstruktur menunggu tinjauan. Angka tersebut tidak berarti terdapat 599 penerima beasiswa. Riwayat penerimaan PT dipisahkan dari progres kuliah, status beasiswa dan izin publikasi.

Fokus B4: katalog dan persyaratan resmi → profil yang cukup → evaluasi/skor backend → penjelasan Gemini → validasi respons → draft pending → pemeriksaan admin/BK → hasil disetujui untuk siswa sendiri. Gemini tidak menentukan fakta penerimaan atau melatih model dengan identitas murid. Skor kecocokan bukan peluang pasti lolos.

Masih membutuhkan data/konfigurasi di luar revisi diagram: katalog beasiswa resmi beserta kriteria yang terisi; nilai dan ekonomi yang diperlukan kriteria; pemeriksaan prestasi; konfirmasi tanggal asesmen; progres penerimaan beasiswa alumni; izin publikasi; integrasi FE dan pengujian antar laptop; kesiapan SMTP, Google, ClamAV dan server live. Diagram memodelkan alur yang tersedia/diatur backend, bukan pernyataan bahwa seluruh konfigurasi sudah berjalan.

Revisi diagram ini tidak mengubah baris database, memberi persetujuan prestasi, membuat tanggal tes, atau menerbitkan data murid.

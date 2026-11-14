# Rekomendasi teratas untuk siswa aktif SMA 8

Perubahan 14 November 2026. Penerima rekomendasi harus mempunyai riwayat kelas SMA (10–12) pada tahun ajaran berjalan, tanggal mulai sudah berlaku, riwayat belum berakhir, dan tidak tercatat lulus. Tahun ajaran dihitung Juli–Juni. Status kuliah aktif milik alumni tidak menjadikannya siswa SMA aktif. Alumni hanya menjadi referensi historis. Tidak ada data siswa atau status kelulusannya yang diubah otomatis.

FE pencarian admin selalu meneruskan aktif_rekomendasi=1. Service sumber BE memeriksa ulang status aktif sehingga preview, Generate Ulang, Simpan, rekomendasi pribadi dan chat tidak dapat dilewati dengan ID alumni. Siswa aktif dapat menargetkan jenjang Kuliah untuk persiapan masuk perguruan tinggi.

## Urutan dan penanda

1. Memenuhi kriteria dengan data lengkap ditempatkan lebih dahulu.
2. Di kelompok tersebut, skor kecocokan kriteria lebih tinggi didahulukan, lalu relevansi TF-IDF/cosine bila skor kriteria setara.
3. Pilihan dengan data belum lengkap dan tanpa syarat yang diketahui gagal berada di bawah pilihan yang memenuhi kriteria. ML dapat mengurutkan eksplorasi tanpa mengubah status kelayakan.
4. Pilihan dengan kriteria gagal tidak mendapat bintang. ID hanya pemecah urutan stabil; bukan bukti program terbaik.

BE mengirim peringkat, unggulan, label_unggulan dan alasan_unggulan. FE menampilkan bintang sesuai metadata BE:
- Paling Sesuai: pemenang yang dapat dibedakan berdasarkan kriteria atau relevansi minat pada skor kriteria setara.
- Pilihan Sesuai Kriteria: hanya satu pilihan yang diketahui memenuhi syarat.
- Prioritas Dipelajari: relevansi minat tertinggi untuk eksplorasi, sementara persyaratan belum dapat dipastikan.
- Tidak ada bintang jika hasil seri tanpa pembeda, minat/katalog tidak memberikan sinyal yang cukup, atau semua pilihan gagal.

Skor kriteria dan cosine bukan probabilitas diterima. Tidak ada pemeringkatan global "terbaik untuk semua siswa". Katalog admin tetap Aktif A–Z lalu Diarsipkan A–Z.

## Dasar sumber resmi yang diperiksa

- KIP-K: https://kip-kuliah.kemdiktisaintek.go.id/panduan — potensi akademik, keadaan ekonomi dan verifikasi PT; bukan batas nilai universal yang dapat dikarang.
- UM jalur prestasi: https://seleksi.um.ac.id/sm-jalur-prestasi/ — persyaratan prestasi/seleksi dan kemungkinan dukungan biaya. Penerimaan jalur prestasi tidak otomatis membuktikan pemberian beasiswa.
- TELADAN UIN Malang: https://pmb.uin-malang.ac.id/ar/beasiswa-teladan/ — kategori kompetensi dan seleksi khusus, berbeda dari Tanoto.
- UPH keperawatan: https://www.uph.edu/wp-content/uploads/fliphtml5/309/files/basic-html/page3.html — cakupan dukungan dan ikatan dinas; brosur ini tidak cukup menetapkan seluruh kriteria dan tanggal penawaran berjalan.

Katalog nyata diperiksa baca-saja. TELADAN (ID 5) diaktifkan kembali atas permintaan pemilik proyek. Hanya is_active dan timestamp diperbarui; seluruh ketentuan lain dibandingkan sebelum/sesudah dan tetap sama. Tombol Aktifkan kini memakai POST /api/beasiswa/{id}/activate dengan JSON kosong, staf saja, tanpa mengirim ulang formulir/kriteria. Ini memperbaiki kegagalan pada program resmi yang tidak memiliki kriteria numerik.

Tidak ada pelatihan supervised atau klaim akurasi penerimaan. Pengujian menggunakan data terisolasi: kasus kriteria versus kemiripan, hasil seri, profil belum lengkap, penolakan alumni, aktivasi idempoten, perlindungan origin, dan tampilan bintang aman.

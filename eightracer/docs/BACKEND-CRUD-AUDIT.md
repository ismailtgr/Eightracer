# Audit CRUD backend — 11 November 2026

Pemeriksaan dilakukan pada proyek utama C:\eightracer\eightracer. Tes tulis memakai SQLite in-memory; data sekolah tidak dipakai sebagai tempat uji tambah/hapus. Pemeriksaan HTTP lokal menggunakan sesi uji terpisah.

## Hasil dan cakupan

- Regresi awal: 182 tes, 1460 assertions, tanpa kegagalan; satu tes XLSX dilewati.
- Ditambahkan CrudCoverageTest untuk siklus tambah–baca–ubah–hapus pada enam jenis referensi, biodata siswa, empat jenis data pendukung, bantuan, dan media sosial.
- Regresi setelah perbaikan: 185 tes (184 lulus, satu XLSX dilewati), 1569 assertions, nol kegagalan. Tes CRUD terarah setelah penambahan pemeriksaan perubahan nilai: 3 tes / 114 assertions lulus.
- Bug yang ditemukan: payload platform tidak sesuai kolom jenis_media pada media_sosial. Diperbaiki melalui alias model sehingga kontrak API dan kolom lama tetap kompatibel. Tidak membutuhkan migration.
- Semua migration pada database utama berstatus Ran saat diperiksa.

| Modul | Perilaku pengelolaan yang diperiksa |
|---|---|
| Referensi kelas, mapel, bantuan, hobi, cita-cita, kampus | Tambah, daftar, ubah, hapus; ID yang sudah dihapus menghasilkan 404 |
| Biodata siswa | Tambah, detail, ubah, hapus; siswa tidak boleh mengelola profil lain |
| Prestasi, organisasi, karakter, saudara | Tambah, baca melalui data-pendukung, ubah, hapus; ID harus milik siswa pada path |
| Bantuan dan media sosial | Tambah, daftar, ubah, hapus; ID lintas siswa ditolak |
| Orang tua, wali, ekonomi | Baca dan simpan/perbarui; kondisi orang tua tertentu memerlukan wali |
| Rapor, presensi, minat, kelas | Simpan/perbarui dengan validasi semester, batas nilai dan riwayat kelas |
| Asesmen | Tambah, baca, koreksi berversi; riwayat tidak ditimpa |
| Alumni dan progres kuliah | Tambah, baca, koreksi, arsip serta pembatasan publikasi/keuangan |
| Katalog beasiswa | Tambah, daftar/detail, ubah, arsip/aktifkan; riwayat rekomendasi dipertahankan |
| Rekomendasi beasiswa | Generate, baca, edit alasan/waktu, sembunyikan/tampilkan, hapus logis dan publikasi ulang; konflik revision ditolak |
| Eligibilitas dan klaster jurusan | Simpan/perbarui, hitung, baca hasil, arsip; perubahan sumber membatalkan hasil lama |
| Impor CSV/Excel | Pratinjau, validasi, scan dan commit; penulisan idempoten dan pemilik batch diperiksa |
| Akun, profil dan autentikasi | Provisioning admin, login, ubah profil/password, logout dan pembatasan sesi/role |

Tes yang sudah ada mencakup rekomendasi, katalog, alumni/progres, privasi, impor, asesmen, ekonomi, autentikasi dan keamanan. Daftar di atas menyatakan perilaku yang tersedia; bukan berarti setiap tabel mempunyai endpoint DELETE fisik. Data audit memakai koreksi/arsip sesuai kontraknya.

## Batas pemeriksaan

- Tes integrasi otomatis memakai database terisolasi dan respons provider palsu. Hasil ini tidak menjamin layanan Gemini, SMTP, atau antivirus eksternal selalu tersedia.
- Pemeriksaan HTTP utama membuktikan endpoint membaca data dan menolak mutasi siswa/ID yang tidak ada; tidak menulis data uji ke database sekolah.
- Akun siswa uji belum terhubung ke profil. Respons personal 404 dan daftar publikasi alumni kosong adalah keadaan data/izin, bukan bukti kegagalan CRUD.
- Belum ada referensi persetujuan publikasi sekolah yang ditulis pengguna; audit tidak mengubah izin publikasi alumni.

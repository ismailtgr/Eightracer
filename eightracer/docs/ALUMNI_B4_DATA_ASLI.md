# Alumni B4: data sekolah dan progres perkuliahan

Diterapkan pada proyek utama C:\eightracer, 4 November 2026. Daftar dan panel detail memakai struktur serta CSS FE yang ada.

## Data sumber dan label

Database utama eightracer_db: 599 penerimaan diterima, 576 alumni unik, 49 kampus terkait. Satu alumni bisa mempunyai beberapa penerimaan; ID detail ialah study_tracer.id, bukan siswa.id.

Nilai lama Lainnya ditampilkan menurut kategori tab sekolah yang cocok dengan prodi, jenjang dan tahun alumni: Jalur Swasta (52), ADIK (5), UM PTKIN (6), Luar Negeri (1). SNBP, SNBT, Mandiri dan jalur spesifik lain dipertahankan. Label kategori sumber bukan penetapan metode seleksi atau keputusan beasiswa. Catatan sumber asli dipertahankan, tidak ditimpa.

Kelas sumber yang berupa daftar diproyeksikan sebagai teks. Field yang belum tersedia tetap null dan ditampilkan -. Tidak ada progres berarti status belum diketahui; Aktif hanya berasal dari status_pendidikan=aktif yang disimpan untuk penerimaan itu. Nol, false dan null dibedakan.

## Kontrak API

Relatif terhadap /api, wajib autentikasi admin/Guru BK berwenang. Tamu 401; siswa 403.

| Method | Path | Fungsi |
|---|---|---|
| GET | /alumni | Paginasi dan filter |
| GET | /alumni/{id} | Detail, progres terkini, pilihan katalog aktif |
| POST | /alumni/{id}/college-progress | Tambah progres |
| PUT | /alumni/{id}/college-progress/{progress} | Koreksi progres dengan versi baru |
| DELETE | /alumni/{id}/college-progress/{progress} | Arsipkan; sejarah tetap tersimpan |
| GET | /alumni/{id}/progress-template | CSV header saja, dapat dibuka Excel |
| POST | /alumni/{id}/progress-imports | Unggah dan pratinjau |
| POST | /alumni/{id}/progress-imports/{batch}/commit | Simpan batch setelah konfirmasi |

Filter daftar: search, perguruan_tinggi_id, program_studi, jalur_masuk, tahun_lulus, page, per_page (1–100, default8), include_filters. Pilihan filter dan pencocokannya memakai label jalur yang sama dengan daftar/detail.

ID siswa/penerimaan ditentukan server. Klien tidak boleh mengganti siswa_id, study_tracer_id atau izin publikasi. Progres pada penerimaan lain tidak dapat diedit/diarsipkan lewat ID yang sedang dibuka.

Payload manual:
- Wajib: periode (teks maks50), semester (1–40), status_pendidikan (aktif/cuti/lulus/nonaktif), tanggal_update (YYYY-MM-DD, tidak di masa depan).
- Opsional/null: ipk_terkini (0–4), penerima_kipk (boolean), nominal_ukt, nominal_ipi. IPI hanya untuk Mandiri.
- beasiswa: [{beasiswa_id, status_beasiswa: proses|diterima|ditolak, nominal_beasiswa: number|null}].
- Tahun lulus harus sudah diketahui pada profil. Jenis biaya dan nama program tidak dibuat otomatis.

POST/PUT sukses 201: {"data":{"id":123,"revision":1}}. Periode sama yang sudah ada harus diedit; koreksi mempertahankan versi sebelumnya. Periode penerimaan tidak boleh dipindahkan saat edit. Publikasi tetap melalui izin terpisah.

## Spreadsheet

1. Buka detail alumni → Sinkronkan Data → Unduh template.
2. Isi data sebenarnya. CSV memakai koma sebagai pemisah (Excel mengutip angka desimal koma); XLSX juga didukung. Maks5MB, 40 baris, satu periode per baris.
3. Kolom wajib: periode, semester, status_pendidikan, tanggal_update.
4. Alias yang didukung: ipk, kipk, ukt, ipi, beasiswa. Nama canonical tetap bisa dipakai.
5. beasiswa boleh berupa ID katalog atau nama persis yang cocok dengan satu program. Bila diisi, status_beasiswa wajib proses/diterima/ditolak. Jangan memasukkan KIP sebagai keputusan diterima tanpa data konfirmasi.
6. Pilih format angka: canonical (3500000, 3.75), atau id (3.500.000, 3,75). KIP-K menerima Ya/Tidak atau 1/0.
7. Pratinjau memperlihatkan tambah/koreksi dan kesalahan per baris. Belum ada penulisan progres.
8. Tekan Simpan hanya jika seluruh baris valid. Seluruh batch tersimpan atomik. Pengulangan commit tidak menggandakan data.

Tanggal Excel dapat berupa tanggal/serial yang sah atau YYYY-MM-DD. Kolom opsional baru yang kosong menjadi null. Pada koreksi lewat impor, kolom kosong mempertahankan nilai lama; gunakan edit manual untuk mengosongkan nilai yang telah dikonfirmasi salah. Duplikasi periode dalam satu batch ditolak; beberapa beasiswa pada satu periode dapat dikelola melalui form manual.

Antivirus wajib tersedia; file terinfeksi/tidak dapat dipindai ditolak. XLSX berformula/makro/konten aktif ditolak. Batch dibatasi pemilik dan penerimaan, memiliki masa berlaku serta digest; sumber berubah setelah pratinjau mengharuskan pratinjau baru. Batch kecil ini disimpan langsung tanpa menunggu worker antrean.

## Sambungan dan pemeriksaan

FE memakai /api/b4/alumni dengan suffix yang sama. Server FE meneruskan cookie HttpOnly sebagai bearer ke API_BASE_URL; token tidak dikirim ke browser. Mutasi dibatasi asal aplikasi, ukuran dan field. Kesalahan server tidak diteruskan sebagai rincian internal.

- BE: 161 tes / 1200 assertions lulus, satu tes XLSX lama dilewati sebelum koreksi normalisasi kelas terakhir; tes alumni diulang setelah koreksi tersebut.
- FE: 9 tes parser/form; TypeScript dan ESLint pada berkas terkait.
- Verifikasi HTTP daftar/detail utama, autentikasi, privasi, filter sumber dan template.
- Simpan, koreksi, pratinjau, commit ulang dan arsip diuji pada database uji terpisah; unggahan melalui ClamAV sebenarnya.
- Tidak menambahkan progres buatan ke database sekolah. Tidak memerlukan migrasi baru.
- Pemeriksaan visual melalui browser otomatis belum dilakukan.

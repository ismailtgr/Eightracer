# Hasil audit data asli sekolah untuk B4

Pemeriksaan dan pemasangan dilakukan pada 4 November 2026, langsung di `C:\eightracer\eightracer` dan database `eightracer_db`.

## Temuan utama

Data asli sudah dimiliki proyek. Ketiga XLSX yang diambil ulang melalui Google Drive memiliki checksum SHA-256 **persis sama** dengan berkas sumber impor 2 November. Jadi, sumber terbaru yang dibagikan bukan kumpulan alumni tambahan yang perlu dimasukkan ulang. Semua 39 tab telah diperiksa kembali, termasuk tab penerimaan, skor, sertifikat, dan rekap.

Pencocokan dengan database aktual menemukan seluruh **697 profil alumni, 599 penerimaan PT, dan 58 kampus** dari batch tersebut. Database keseluruhan memiliki 872 profil siswa/alumni karena juga berisi profil dari sumber lain. Penggabungan identitas lama dan pemetaan kampus/prodi dicocokkan melalui manifest sumber yang checksum-nya terverifikasi, bukan pencarian nama yang mirip.

| Tahun ajaran | Tab diperiksa | Baris sumber penerimaan | Penerimaan terpasang | Penerimaan belum lengkap | Isian kolom KIP/beasiswa tidak kosong |
|---|---:|---:|---:|---:|---:|
| 2023/2024 | 9 | 211 | 89 | 121 | 91 |
| 2024/2025 | 14 | 266 | 262 | 3 | 252 |
| 2025/2026 | 16 | 249 | 248 | 0 | 231 |
| Total | 39 | 726 | 599 | 124 | 574 |

Selisih baris sumber penerimaan juga mencakup satu identitas yang perlu dicocokkan dan dua laporan penerimaan berulang yang sudah digabung. Tab rekap/skor/sertifikat tidak dihitung sebagai penerimaan atau siswa baru. Banyak penerimaan 2024 tidak mencantumkan jenjang studi; data aslinya tetap ada pada catatan profil, tetapi jenjang D3/D4/S1 tidak ditebak.

## Yang benar-benar dipasang sekarang

Migrasi `2026_11_04_000001_create_school_scholarship_observations` sudah dijalankan. Total migrasi utama menjadi 53. Tabel baru `school_scholarship_observations` menyimpan **574 catatan sumber asli**, bukan data sintetis:

- 573 catatan terhubung ke profil melalui `siswa_id`.
- 522 terhubung ke penerimaan PT melalui `study_tracer_id`.
- 52 belum mempunyai penerimaan PT lengkap; kampus/prodi asli tetap tersimpan pada kolom sumber. Di antaranya ada satu catatan yang belum mempunyai identitas siswa yang pasti.

Hubungan data: `school_scholarship_observations.siswa_id → siswa.id`, dan bila penerimaan sudah lengkap, `study_tracer_id → study_tracer.id → perguruan_tinggi_id → perguruan_tinggi.id`. Program studi, jenjang, dan jalur dibaca dari penerimaan yang sama, sehingga beasiswa dari satu catatan tidak dipasangkan ke kampus/prodi lain milik alumni tersebut.

Setiap catatan memuat ID file Drive, checksum file, tab, baris, kolom, nilai asli, tahun lulus, serta kampus dan prodi sesuai sumber. Nilai asli dipertahankan. Impor pertama menambah 574 catatan; impor ulang menambah **0**, mengenali 574 catatan yang sama. Perubahan pemetaan untuk checksum dan sel yang sama akan ditolak untuk diperiksa, bukan menimpa diam-diam.

Cadangan terenkripsi dibuat dan pembacaan ulang cadangan berhasil. Isi tabel lama siswa, penerimaan, kampus, katalog, progres, riwayat beasiswa, dan rapor dibandingkan sebelum/sesudah dan tetap sama. Tidak dibuat akun siswa asli, keputusan beasiswa, nominal biaya, tanggal progres, atau izin publikasi baru.

## Makna kolom beasiswa

| Isi sumber | Jumlah catatan | Disimpan sebagai | Batas penafsiran |
|---|---:|---|---|
| KIP/KIPK `Ya` atau `Tidak` | 241 | `indikator_kip` dengan boolean sesuai isian |69 Ya dan 172 Tidak; judul kolom belum menetapkan status pelamar/penerima |
| `KIP-K`, `KIP K`, `KIPK`, `KIP` pada kolom BEASISWA | 13 | `referensi_kip` | Nama/isian sumber, belum keputusan penerima |
| `PRESTASI BASKET` | 1 | `referensi_beasiswa` | Kategori asli, belum syarat/nominal resmi |
| Beasiswa dengan ikatan dinas Universitas Pelita Harapan | 1 | `referensi_beasiswa` | Referensi asli, belum status keputusan atau persyaratan lengkap |
| `BEASISWA TELADAN` | 1 | `referensi_beasiswa` | Penyelenggara dan syarat tidak ditambahkan berdasarkan dugaan |
| `BEBAS IPI` | 1 | `pembebasan_biaya` | Pembebasan biaya IPI; bukan nominal UKT atau bukti beasiswa penuh |
| `V` | 38 | `kode_sumber` | Tanda pada sumber dipertahankan, arti operasionalnya perlu dijelaskan |
| `TPKS` | 5 | `kode_sumber` | Kode dipertahankan; tidak otomatis dijadikan program beasiswa |
| Nama Retsumeikan Asia Pacific University pada kolom BEASISWA | 1 | `keterangan_belum_jelas` | Isian berupa nama kampus, belum nama/status beasiswa yang jelas |
| `-` / `--` | 272 | `tanpa_keterangan` | Tidak diubah menjadi gagal beasiswa atau bukan penerima |

**574 bukan jumlah penerima beasiswa.** Ini jumlah sel sumber tidak kosong, termasuk tanda minus dan jawaban Tidak. Keaslian data sekolah tidak dipersoalkan; yang dipertahankan adalah perbedaan antara isi sumber dan kesimpulan yang belum dinyatakan sumber.

Sebagian informasi beasiswa dahulu sudah tersimpan di JSON `siswa.catatan.data_sumber`. Kekurangannya adalah belum menjadi relasi yang dapat ditelusuri melalui API B4. Tabel baru menyelesaikan bagian tersebut tanpa menghapus catatan sumber lama.

## API untuk FE: membaca data asli

Base URL data utama melalui Tailscale:

```text
https://laptop-3qspir74.tail5f273f.ts.net/api
```

Gunakan akun Admin/BK pada database **utama**, bukan akun `admin.b4.uji` milik database sintetis. Akun admin sementara utama yang sudah ada beremail `admin.uji@eightracer.test`; password tetap di berkas pribadi `C:\eightracer\.local\akun-uji\Akun_Sementara.txt`. Tidak ada password/token dalam dokumen atau Git.

| Method/path relatif `/api` | Fungsi |
|---|---|
| GET `/riwayat-beasiswa-sumber/ringkasan` | Jumlah sumber, kelengkapan hubungan, kategori per angkatan, nilai referensi |
| GET `/riwayat-beasiswa-sumber` | Paginator 20, maksimal 100 melalui `per_page`; sumber asli dan relasi siswa/penerimaan/kampus |
| GET `/riwayat-beasiswa-sumber?tahun_lulus=2026` | Filter angkatan |
| GET `/riwayat-beasiswa-sumber?jenis_keterangan=referensi_beasiswa` | Tiga referensi kategori/nama beasiswa yang tercantum |
| GET `/riwayat-beasiswa-sumber?siswa_id={id}` | Semua catatan untuk satu profil |
| GET `/riwayat-beasiswa-sumber?study_tracer_id={id}` | Catatan yang terkait penerimaan PT tertentu |

Autentikasi bearer, `Accept: application/json`. Kedua endpoint hanya untuk staf sekolah, bukan siswa umum. Tidak ada endpoint yang mempublikasikan identitas atau isi spreadsheet kepada siswa/LLM. Respons daftar menyediakan `sumber.url`, `sumber.sheet`, `sumber.cell`, `nilai_sumber`, `jenis_keterangan`, `indikator_kip`, relasi `siswa`, dan `study_tracer.perguruan_tinggi`. Field `status_beasiswa` pada respons selalu `belum_dikonfirmasi` karena keputusan beasiswa tidak dinyatakan oleh isian tersebut.

API data asli telah diuji pada server utama dan melalui HTTPS Tailscale: Admin 200 (574 catatan), Siswa 403, tanpa login lokal 401. FE perlu menghubungkan halaman riwayat/analisis ke endpoint ini; tampilan FE tidak diubah dalam pekerjaan ini. `/beasiswa` tetap katalog program dan kriteria, sehingga tidak diisi dengan sel minus, kode TPKS, nama kampus, atau kriteria perkiraan.

Koleksi `b4-uji-20261105/B4_Data_Asli.postman_collection.json` dan environment `B4_Data_Asli.postman_environment.json` menyediakan enam request login/baca/logout untuk data utama. Password/token awal kosong. Simpan password hanya secara pribadi; login admin yang sama mencabut sesi admin sebelumnya. Koleksi ini tidak menyediakan operasi tambah/edit/arsip data sekolah.

## Yang sudah bisa dan yang masih memerlukan data

Data yang tersedia bisa digunakan untuk penelusuran alumni berdasarkan kampus, prodi, jalur, angkatan, dan catatan KIP/beasiswa sesuai sumber. Ada 186 skor UTBK tervalidasi dari impor lama. Skor UTBK tidak disamakan dengan nilai TKA atau rapor semester 1–5.

Untuk rekomendasi kelayakan dan pembandingan hasil alumni, masih diperlukan:

1. Katalog program yang berlaku: nama/penyelenggara resmi, ketentuan, periode, dan kriteria yang memang dipersyaratkan. Tiga buku penerimaan belum memuat ketentuan itu secara lengkap.
2. Status beasiswa yang jelas per alumni/program: pelamar/proses/diterima/ditolak, periode, dan rujukan konfirmasi. Penerimaan di PT bukan keputusan penerimaan beasiswa.
3. Nominal UKT/IPI yang dibayar serta IPK per semester untuk dashboard keuangan. Tiga file tidak menyediakan rangkaian nominal dan IPK tersebut.
4. Rapor semester 1–5 dan latar ekonomi bila menjadi syarat program. Tabel `nilai_rapor` dan `orang_tua` pada database utama masih kosong saat audit.
5. Kesesuaian identitas, izin pemakaian agregat/publikasi pada modul yang memerlukannya, dan arti kode V/TPKS untuk penafsiran lebih lanjut.

Mesin AI tidak diberi kesimpulan bahwa 574 orang menerima beasiswa. Model klasifikasi peluang lolos juga tidak dilatih dengan jawaban KIP Tidak atau tanda minus sebagai label gagal. TF-IDF/cosine bisa digunakan untuk kesesuaian teks katalog setelah katalog resmi tersedia; ia tidak menggantikan data keputusan beasiswa atau aturan kelayakan.

## Data sintetis dan pengujian

Data sintetis **tidak diperlukan untuk isi fitur B4 yang punya sumber asli**. Database `eightracer_b4_uji_20261105` hanya pilihan untuk uji tambah/edit/arsip dan skenario gagal tanpa mengubah data sekolah. Data asli tetap di database utama dan kini memiliki riwayat sumber yang lebih terhubung. Database uji tidak diisi dengan salinan identitas sekolah pada pekerjaan ini; skemanya saja disamakan menjadi 53 migrasi.

Tes backend: 129 tes, 900 assertions, tidak ada kegagalan, satu tes impor XLSX lama dilewati karena keterbatasan pemeriksaan berkas pada lingkungan Windows. Tes baru memeriksa idempotensi, checksum, pasangan siswa/penerimaan, rollback saat pemetaan salah, pemisahan arti indikator/kode/biaya, dan pembatasan API staf.

Sumber yang diambil dan dicocokkan:

- [2023/2024](https://docs.google.com/spreadsheets/d/1-wq5eGYqf91L_1nOOlBRo3KVswRK4qSI/edit)
- [2024/2025](https://docs.google.com/spreadsheets/d/1CPKUio-wXx0jLTn8Nj4ibsM3kOsyNC10/edit)
- [2025/2026](https://docs.google.com/spreadsheets/d/1i3igBq5PbH8TPk60Pz75M2Qfz8MrVYsA/edit)

Bukti pemetaan dan backup pribadi berada di `.local/school-audit-20261104`, diabaikan Git. Dokumen ini hanya memuat ringkasan dan lokasi sumber, tanpa nama/nomor kontak alumni.

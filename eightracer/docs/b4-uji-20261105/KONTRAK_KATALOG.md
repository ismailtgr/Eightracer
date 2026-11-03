# Kontrak katalog beasiswa B4

Revisi 4 November 2026. Path relatif terhadap base URL `/api`. Semua endpoint katalog memerlukan bearer token Admin atau Guru BK yang memiliki akses staf terverifikasi. Akun siswa mendapat 403. Tanpa token: 401. Header request: `Accept: application/json`; tambahkan `Content-Type: application/json` untuk body JSON.

## Endpoint dan envelope

| Method | Path | Input | Respons berhasil |
|---|---|---|---|
| GET | `/beasiswa` | `search` opsional maks100, `jenjang=SMA|Kuliah|Keduanya`, `page` | 200 paginator Laravel `{data:[...],current_page,last_page,per_page,total,...}` |
| GET | `/beasiswa/{id}` | Tanpa body | 200 `{data:{...beasiswa,kriteria_kelayakan_beasiswa:[...]}}`; 404 bila ID tidak ada |
| POST | `/beasiswa` | Body katalog dan `kriteria` | 201 `{data:{...}}` |
| PUT | `/beasiswa/{id}` | Body katalog dan `kriteria`; wajib nama, jenjang, seluruh kriteria | 200 `{data:{...}}` |
| DELETE | `/beasiswa/{id}` | **Tanpa body** | 200 `{message:"Beasiswa diarsipkan; riwayat rekomendasi tetap tersedia."}` |
| PUT | `/beasiswa/{id}` | Body lengkap yang sama, `is_active:true` | 200 `{data:{...,is_active:true,...}}` untuk aktifkan kembali |

GET detail baru ditambahkan dan sudah diuji pada proyek utama. Tidak ada endpoint khusus `/activate`, `/restore`, atau PATCH katalog. DELETE adalah arsip (`is_active=false`), bukan hapus permanen. Detail dan daftar tetap memuat beasiswa arsip. Tidak ada filter `is_active` pada API daftar saat ini. Paginasi tetap 20 per halaman; `per_page` bukan opsi katalog. Filter jenjang SMA/Kuliah juga menyertakan Keduanya.

## Field katalog

| Field | Aturan |
|---|---|
| `nama_beasiswa` | Wajib, string maks255 |
| `jenjang` | Wajib, `SMA`, `Kuliah`, atau `Keduanya` (huruf sesuai) |
| `kriteria` | Wajib array 1–30 entri, termasuk saat PUT |
| `is_active` | Opsional boolean; selalu kirim boolean eksplisit saat tambah/aktifkan |
| `penyelenggara` | Null atau string maks255 |
| `deskripsi` | Null atau teks polos maks3000 |
| `tanggal_buka`, `tanggal_tutup` | Null atau `YYYY-MM-DD`; tutup >= buka jika buka dikirim |
| `link_pendaftaran` | Null atau URL HTTP/HTTPS maks500; bukan javascript/data URL |
| `durasi` | Null atau angka >0 sampai100 |
| `satuan_durasi` | `tahun|semester|bulan`, wajib jika durasi ada |
| `periode_apply` | Null atau string maks255 |
| `program_eligibilitas_id` | Null atau ID program yang sudah ada; wajib untuk tiga tipe kriteria di bawah |

PUT mengganti **seluruh daftar kriteria**; kriteria lama dihapus dan dibuat kembali, sehingga ID kriterianya bisa berubah. Jangan mengirim ID kriteria, beasiswa_id, atau field read-only di dalam setiap entri kriteria. Field katalog opsional yang tidak dikirim pada PUT mempertahankan nilai sebelumnya; untuk mengosongkan kirim null sesuai validasi. Untuk edit/aktifkan, ambil detail terbaru dan kirim kembali field yang didukung.

## Bentuk kriteria dan operator

```json
{
  "nama_kriteria": "Rapor minimum",
  "tipe_kriteria": "rata_rapor",
  "operator": ">=",
  "nilai_kriteria": "85",
  "bobot": 2
}
```

Nama wajib maks255. `nilai_kriteria` **selalu string**, meskipun berisi angka. Untuk angka gunakan titik desimal, tanpa Rp, persen, atau pemisah ribuan. Contoh benar `"3000000"`, bukan `"Rp 3.000.000"` atau angka JSON `3000000`.

Operator numerik valid persis `=`, `!=`, `>=`, `<=`, `>`, `<`. `==`, `===`, `between`, `in`, `contains` tidak valid. `status_eligible` hanya `=` dan `!=`.

| `tipe_kriteria` | Isi `nilai_kriteria` / batas validasi | Makna data |
|---|---|---|
| `nilai_tka` | Angka 0–100 | Nilai TKA siswa |
| `rata_rapor` | Angka 0–100 | Rata-rata nilai rapor; baru diketahui bila semester 1–5 tersedia |
| `penghasilan_orang_tua` | Angka 0–9.999.999.999.999 | Total penghasilan ayah+ibu dalam rupiah; keduanya perlu terisi |
| `persentase_kehadiran` | Angka 0–100 | Rekap semester terbaru |
| `jumlah_prestasi` | Angka 0–9.999.999.999.999 | Prestasi terverifikasi; kelengkapan perlu dikonfirmasi |
| `skor_prestasi` | Angka 0–100 | Skor menurut bobot dan metode program; **butuh program_eligibilitas_id** |
| `bantuan_sosial_sesuai` | Angka 0–1 | Kecocokan bantuan sesuai program; **butuh program_eligibilitas_id** |
| `jumlah_organisasi` | Angka 0–9.999.999.999.999 | Jumlah riwayat organisasi |
| `poin_karakter` | Angka -1.000.000 sampai1.000.000 | Total poin disiplin/karakter |
| `ipk` | Angka 0–4 | IPK progres terkini alumni yang aktif/cuti |
| `semester_kuliah` | Angka 1–40 | Semester progres kuliah terkini |
| `status_eligible` | Persis `Eligible` atau `Tidak Eligible` | Status program terkait; **butuh program_eligibilitas_id** |

Program tersedia melalui GET `/program-eligibilitas` (paginator20). Untuk penggunaan nyata pilih program aktif. Program yang tidak aktif atau data program belum lengkap tidak memberikan status yang diketahui. `Belum dinilai` bukan nilai threshold yang boleh disimpan pada `status_eligible`. Jangan menggunakan `ipk` untuk nilai SMA.

Bobot: null/tidak dikirim berarti bobot efektif1; bila diisi angka antara **0.01–999.99**. Simpan maksimal dua angka desimal sesuai kolom DB. Bobot0 dan negatif ditolak. Bobot relatif, **tidak wajib total100**.

Skor = `100 × jumlah bobot kriteria yang terpenuhi / jumlah seluruh bobot`. Contoh bobot2 dan1, hanya kriteria pertama terpenuhi → 66,6667. `memenuhi=true` hanya jika seluruh kriteria terpenuhi; skor besar bukan jaminan lolos. Data belum tersedia → status `belum_diketahui`, bukan nol; bobot tetap masuk penyebut dan `lengkap=false`. Status checklist lain: `terpenuhi`, `belum_terpenuhi`. Nilai akademik/ekonomi tidak boleh dikarang untuk menghilangkan status kosong.

## Mengisi form edit

1. GET `/beasiswa/{id}`.
2. Gunakan `response.data` untuk field katalog. Relasi baca bernama `kriteria_kelayakan_beasiswa`.
3. Petakan relasi tersebut menjadi array kirim `kriteria` dengan hanya `nama_kriteria`, `tipe_kriteria`, `operator`, `nilai_kriteria`, `bobot`.
4. `bobot`/`durasi` pada respons bisa berupa string desimal; parse untuk form dan validasi sebelum kirim. `is_active` adalah boolean. Tanggal respons dapat berupa ISO datetime; normalisasikan ke sepuluh karakter tanggal untuk input date.
5. Simpan memakai PUT dengan nama, jenjang, seluruh kriteria dan field opsional yang ingin dipertahankan. Muat detail baru setelah berhasil; jangan mengandalkan ID kriteria sebelumnya.

Aktifkan ulang memakai proses yang sama dengan `is_active:true`. Jangan mengirim hanya `{is_active:true}` karena nama, jenjang, kriteria tetap wajib. Body arsip kosong. Tidak ada penjumlahan bobot di FE yang menggantikan hasil evaluator BE.

## Error dan sesi

422 biasanya `{message:"Input tidak valid.",errors:{"kriteria.0.operator":["..."]}}`. Sebagian pemeriksaan aturan bisnis memberi `{message:"Permintaan tidak dapat diproses."}` tanpa `errors`; FE wajib punya pesan fallback. Jangan mensyaratkan bahasa tertentu pada teks validator.

401 minta login ulang, 403 akses ditolak, 404 ID tidak ada, 409 konflik/revisi berubah, 429 batas permintaan, 503 layanan belum tersedia. Jangan tampilkan token atau stack trace. Perlakukan teks katalog sebagai teks polos, tanpa `dangerouslySetInnerHTML`.

Sesi Admin/BK 72 jam dan login admin baru mencabut token admin lama. Sesi siswa 168 jam. Dua penguji yang memakai akun admin yang sama dapat saling mengakhiri sesi; koordinasikan atau buat akun uji tambahan melalui BE. Nilai `expires_at` dari respons login adalah acuan FE.

Lihat `Contoh_Payload_Beasiswa.json`, `Contoh_Response_Terverifikasi.json`, dan koleksi Postman di folder ini untuk contoh yang cocok dengan implementasi.

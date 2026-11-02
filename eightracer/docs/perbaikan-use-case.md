# Perbaikan kontrak API use case — 2 November 2026

Empat kegagalan audit diperbaiki pada katalog beasiswa, referensi prestasi alumni dan tren beasiswa per periode. Tidak membutuhkan migrasi atau perubahan .env. Seluruh endpoint tetap menggunakan otorisasi yang sudah ada.

## Katalog beasiswa — admin

`GET /api/beasiswa?search=prestasi&jenjang=SMA&page=2`

- `search`: string maksimal 100 karakter, mencari nama beasiswa. Query memakai parameter database.
- `jenjang`: `SMA`, `Kuliah`, atau `Keduanya`. Filter SMA/Kuliah juga menyertakan beasiswa Keduanya karena berlaku untuk jenjang tersebut; filter Keduanya hanya menampilkan Keduanya.
- Kedua filter dapat digabungkan. Pagination tetap 20 record per halaman, dengan filter dipertahankan pada URL halaman berikutnya/sebelumnya. Input tidak valid mendapat 422; siswa tetap tidak boleh membuka katalog admin.

## Jejak alumni — siswa

`GET /api/jejak-alumni` kini memuat `data[].prestasi`; detail `GET /api/jejak-alumni/{id}` memuat `data.masuk_pt.prestasi` serta proyeksi yang sama di timeline masuk PT.

Prestasi berupa array, termasuk saat kosong. Field yang boleh tampil: `id`, `jenis_prestasi`, `nama_kompetisi`, `bidang`, `tingkat`, `peringkat`, `penyelenggara`, `tahun`. Hanya prestasi yang dihubungkan ke penerimaan PT dan milik alumni yang sama. URL bukti privat, pivot, siswa_id dan bobot internal tidak dikirim. Izin publikasi penerimaan tetap wajib; kontak WA tetap disembunyikan.

## Tren beasiswa — admin

`GET /api/analitik-beasiswa` tetap mempertahankan field lama. Setiap item `data.tren_periode` kini memiliki array `beasiswa`:

```json
{
  "periode": "Semester 1 - 2026",
  "jumlah_alumni": 2,
  "penerima_kipk": 0,
  "beasiswa": [
    {
      "beasiswa_id": 1,
      "nama_beasiswa": "Contoh beasiswa",
      "jumlah_alumni": 2,
      "status": {"proses": 0, "diterima": 1, "ditolak": 1}
    }
  ]
}
```

Angka contoh adalah sintetis. Setiap alumni dihitung sekali per beasiswa dan periode. Jika satu alumni memiliki beberapa catatan penerimaan PT pada periode yang sama, gunakan status catatan dengan tanggal_update paling baru, lalu ID terbesar jika tanggal sama. Revisi lama (`is_current=false`) tidak dihitung. Nama menggunakan snapshot historis bila tersedia. Filter `beasiswa_id` membatasi tren dan distribusi pada beasiswa yang dipilih. Tidak mengirim daftar identitas/nominal ke struktur tren.

## Bukti dan batas pengujian

Empat tes yang tadinya gagal kini lolos. Tes penerimaan diperluas menjadi 13 kasus untuk pagination, filter lintas jenjang, validasi query, privasi prestasi, revisi histori dan hitungan alumni unik. Regresi: **80 tes, 79 lolos, 0 gagal, 1 dilewati; 593 assertions**. Tes impor XLSX penuh masih dilewati karena kendala native is_readable di lingkungan Windows terkelola, sama seperti sebelum perbaikan.

Pengujian menggunakan source salinan dan SQLite dalam memori; provider disimulasikan. UI FE, provider sungguhan, serta unggah/import pada server tujuan masih perlu uji terpisah. Tidak ada data sekolah atau secret yang digunakan dalam fixture.

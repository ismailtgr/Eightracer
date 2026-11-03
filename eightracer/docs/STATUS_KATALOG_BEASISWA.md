# Kontrak status katalog beasiswa

Dipakai oleh GET `/api/beasiswa`, GET detail `/api/beasiswa/{id}`, dan respons POST/PUT. Pembaruan 4 November 2026 menambahkan dua field hasil perhitungan, tanpa perubahan tabel atau migrasi database.

## Status program

`is_active` tetap merupakan flag pengelolaan katalog. `true` menghasilkan `status_program: "aktif"`; `false` menghasilkan `"diarsipkan"`. Berakhirnya periode tidak otomatis mengarsipkan program atau menghapus riwayatnya.

## Status pendaftaran

BE menghitung `status_pendaftaran` pada setiap respons memakai tanggal kalender **Asia/Jakarta (WIB)**. FE membaca field ini dan tidak menebak status dari nama bulan, jam laptop, atau `is_active` saja.

| Nilai API | Label FE | Aturan |
|---|---|---|
| `tidak_tersedia` | Tidak tersedia | Program diarsipkan; mengalahkan status tanggal |
| `ditutup` | Ditutup | Tanggal tutup ada dan hari ini sudah melewatinya |
| `belum_dibuka` | Belum dibuka | Tanggal buka ada dan hari ini belum mencapainya |
| `dibuka` | Dibuka | Program aktif, kedua tanggal ada/valid, hari ini berada dalam rentang termasuk kedua batas |
| `belum_ditentukan` | Jadwal belum ditentukan | Tanggal belum cukup untuk menyatakan dibuka, atau data lama mempunyai urutan tanggal tidak valid |

Tanggal tutup berlaku sampai akhir hari WIB: tutup 31 Oktober berarti masih dibuka pada 31 Oktober 23:59 WIB dan ditutup mulai 1 November 00:00 WIB. Tidak ada job yang mengubah `is_active` pada tengah malam; status tanggal diperbarui ketika API diminta lagi.

Contoh pada **4 November 2026**:

```json
{
  "is_active": true,
  "status_program": "aktif",
  "tanggal_buka": "2026-09-01T00:00:00.000000Z",
  "tanggal_tutup": "2026-10-31T00:00:00.000000Z",
  "periode_apply": "September–Oktober",
  "status_pendaftaran": "ditutup"
}
```

Tanggal dapat diserialisasi sebagai ISO datetime oleh Laravel, tetapi domainnya tetap SQL DATE. FE mengambil sepuluh karakter tanggal untuk input date, bukan menggeser tanggal tersebut ke zona waktu lain.

Jika yang dikirim hanya `periode_apply: "September–Oktober"` dan kedua tanggal kosong, hasilnya `status_program: "aktif"` serta `status_pendaftaran: "belum_ditentukan"`. Tahun dan tanggal tidak dikarang dari tulisan bulan. Untuk periode yang pasti, POST/PUT mengirim `tanggal_buka: "2026-09-01"` dan `tanggal_tutup: "2026-10-31"` bersama payload katalog yang diwajibkan.

## Aturan kirim dan edit

- FE boleh mengirim `is_active` dan tanggal sesuai wewenang admin/BK.
- `status_program` dan `status_pendaftaran` hanya dibaca; jangan kirim kembali pada POST/PUT, karena akan ditolak 422.
- Nama, jenjang, dan seluruh kriteria tetap wajib pada PUT seperti kontrak katalog sebelumnya.
- Tanggal tutup harus sama atau setelah tanggal buka. Pemeriksaan juga memakai tanggal lama yang dipertahankan bila salah satu field tanggal tidak dikirim saat PUT.
- Mengaktifkan kembali program yang periodenya sudah lewat menghasilkan program Aktif dengan pendaftaran Ditutup. Jadwal baru perlu diisi secara terpisah bila ada periode baru yang resmi.
- Rekomendasi tetap dapat mencakup program yang belum dibuka sebagai persiapan, tetapi program yang tanggal tutupnya sudah lewat dikecualikan menurut WIB.

Status ini khusus **program dan jadwal katalog**. Status hasil beasiswa alumni seperti `proses`, `diterima`, `ditolak`, atau catatan sumber `belum_dikonfirmasi` mempunyai makna terpisah dan tidak diganti oleh status jadwal.

FE lokal sudah menampilkan kolom **Status Program** dan **Status Pendaftaran**. Field status baru yang belum tersedia pada server BE versi lama ditampilkan sebagai Jadwal belum ditentukan; FE tidak menebak tanggal atau status Dibuka.

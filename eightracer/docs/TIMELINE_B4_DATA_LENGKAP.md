# Timeline Individu B4 — identitas tampil, riwayat yang belum ada tetap kosong

Perilaku halaman sesuai arahan pengguna 4 November 2026: tampilkan alumni yang sama dengan Alumni di PT, sekalipun progres dan tanggal belum lengkap. Nama, kampus, prodi, jalur dan tahun lulus berasal dari database; bagian timeline tetap terlihat. Nilai belum diketahui ditampilkan -.

## API dan pemetaan

Halaman memakai GET /api/b4/alumni tanpa timeline_complete; server FE meneruskan ke GET /api/alumni. Detail memakai GET /api/b4/alumni/{id}, yang meneruskan ke GET /api/alumni/{id}. ID adalah study_tracer.id; beberapa penerimaan pada satu alumni tetap terpisah.

GET detail BE menambahkan education_events yang memang diketahui. Adapter FE melengkapi tiga posisi tampilan (Masuk SMA, Lulus SMA, Masuk PT) dengan date=null/year=null jika sumber belum memiliki waktunya. Ini placeholder tampilan, tidak disimpan ke database dan tidak dianggap peristiwa yang sudah dikonfirmasi.

Nama kampus/prodi tetap terlihat saat waktu masuk PT kosong. Tahun lulus ditampilkan sebagai tahun jika tanggal lengkap belum ada; tidak dibuatkan bulan atau hari. Tidak ada asumsi tahun masuk SMA= tahun lulus minus3.

Jika belum ada progres, kartu Status Terkini, panel SMA, panel PT dan detail semester tetap dirender dengan -. Delapan posisi semester kosong mengikuti struktur FE; tidak membuat periode, nilai atau beasiswa contoh. Setelah progres asli tersimpan, pilihan semester dan detailnya mengikuti record tersebut. UKT0 dan KIP-K=false dibedakan dari null.

Pencarian, filter kampus/prodi/jalur, pemilihan penerimaan dan paginasi memakai data yang sama dengan Alumni di PT. Kelola Progress membuka detail ID penerimaan yang sama. Tamu/siswa tidak berwenang mengakses arsip admin. NISN mentah, WA, email dan catatan sumber tidak diteruskan.

## Filter opsional backend

timeline_complete=1 tetap tersedia untuk konsumen API yang secara eksplisit membutuhkan riwayat lengkap, tetapi TIDAK dipakai halaman Timeline Individu. Ini bukan syarat menampilkan identitas.

## Data dan pemeriksaan

Database utama:599 penerimaan,576 alumni unik,49 kampus terkait. Tahun lulus tersedia; tanggal masuk SMA/PT dan progres belum tercatat. Halaman tetap menampilkan identitas serta seluruh panel dengan placeholder.

- Backend168 tes /1256 assertions tanpa kegagalan;1 tes XLSX lama dilewati.
- Frontend15 tes parser/form/render lulus, termasuk merender panel dengan progres kosong dan memastikan permintaan timeline tidak memakai filter kelengkapan.
- TypeScript/ESLint berkas terkait lulus.
- Pemeriksaan HTTP memverifikasi identitas/detail yang sama,599 penerimaan,49 kampus, tiga tahap tampilan, null untuk tanggal yang belum ada, privasi dan pembatasan akses.
- Tidak memerlukan migrasi atau pengisian data buatan. Pemeriksaan visual browser otomatis belum dilakukan.

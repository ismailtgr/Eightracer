# Timeline Individu B4 — alumni dengan data lengkap

Diterapkan 4 November 2026 pada proyek utama. Halaman /admin/timeline-individu menggunakan API sekolah, bukan profil/semester contoh. Komponen dan CSS FE yang sedang dipakai tetap menjadi dasar tampilan.

## Arti lengkap

Penyaring per penerimaan (study_tracer.id), bukan per nama saja:
- Penerimaan berstatus diterima; nama alumni, kampus, prodi, jenjang dan jalur tersedia.
- Tanggal masuk SMA tercatat.
- Waktu lulus SMA diketahui: tanggal penuh atau tahun lulus.
- Waktu masuk PT diketahui: tanggal penuh atau tahun.
- Setidaknya satu progres kuliah terkini untuk penerimaan itu, dengan periode, semester1–40, tanggal pembaruan, status pendidikan, IPK0–4, UKT dan status KIP-K yang diketahui.
- Progres terbaru ditentukan tanggal pembaruan, lalu ID jika sama tanggal. Progres lama lengkap tidak dapat meloloskan progres terbaru yang belum lengkap.
- Kronologi harus masuk akal dan tidak di masa depan; tahun/tanggal yang saling bertentangan ditahan.

UKT0 serta KIP-K=false adalah data sah, bukan kosong. NISN, email akun, program beasiswa dan IPI tidak dijadikan syarat wajib: tidak setiap alumni memilikinya. Beasiswa hanya ditampilkan sebagai diterima jika keputusan diterima memang tercatat. Kolom opsional/riwayat lama yang belum diketahui tetap -. Tidak diwajibkan delapan semester atau dibuatkan semester masa depan.

## Kontrak

Relatif terhadap /api, hanya admin/Guru BK berwenang:
- GET /alumni?timeline_complete=1&include_filters=1 : paginator yang hanya memuat alumni lengkap. Menambahkan timeline_complete_only=true. Filter kampus/prodi/jalur juga hanya dari kelompok lengkap.
- GET /alumni/{id}?timeline_complete=1 : detail lengkap, atau404 jika penerimaan belum memenuhi syarat. ID adalah ID penerimaan.
- Filter nama, kampus, prodi, jalur, tahun lulus dan paginasi mengikuti endpoint alumni yang sudah ada.
- Tanpa flag, daftar/detail Alumni di PT tetap menampilkan data sekolah yang tersedia; penyaring tidak menghapus atau mengubah data.
- Detail menambahkan timeline_complete (boolean), education_events [{type,label,date,year}], serta progress terkini. date=null dengan year berarti sumber hanya mempunyai tahun; bulan/hari tidak ditebak.
- Respons tidak memuat NISN mentah, WA, email, catatan sumber mentah atau token.

Server FE meneruskan permintaan melalui /api/b4/alumni dengan timeline_complete=1 menggunakan cookie HttpOnly. FE menolak respons jika backend tidak menyatakan penyaring sudah diterapkan. Detail juga diperiksa kelengkapannya sebelum diteruskan.

## Tampilan

Pencarian/filter dan pemilihan alumni menggunakan hasil API lengkap saja. Dua penerimaan milik satu alumni tetap terpisah. Semester yang ditampilkan berasal dari progres tercatat, dengan pilihan untuk membuka detail tiap periode. Tombol Kelola Progress mengarah ke detail penerimaan yang sama.

Halaman membedakan sedang memuat, belum ada alumni lengkap, tidak ada hasil filter, sesi tidak sah, dan koneksi gagal. Tidak kembali ke data contoh jika hasil kosong/gagal. Nilai tahun saja tetap ditampilkan sebagai tahun; rata-rata rapor tidak dibuat-buat.

## Temuan database utama dan validasi

Pada pemeriksaan 4 November2026:
- 599 penerimaan diterima, seluruhnya memiliki tahun lulus.
- 0 mempunyai tanggal masuk SMA; 0 mempunyai tanggal lulus penuh.
- 0 mempunyai tahun/tanggal masuk PT.
- 0 mempunyai progres perkuliahan.
- Maka0 penerimaan memenuhi kriteria timeline lengkap saat ini. Pilihan dan timeline akan terisi otomatis setelah data sebenarnya dilengkapi. Tidak ada penulisan data buatan ke database sekolah.

Pemeriksaan:
- Backend168 tes /1256 assertions tanpa kegagalan; satu tes XLSX lama dilewati.
- Frontend13 tes parser/form lulus, termasuk tahun tanpa bulan, nol versus null, penolakan data tidak lengkap dan pemisahan keputusan beasiswa.
- TypeScript dan ESLint berkas terkait lulus.
- 13 pemeriksaan HTTP pada FE/BE lokal lulus: data kosong yang sah, filter, privasi, penolakan ID belum lengkap, autentikasi dan halaman tersedia.
- Tidak memerlukan migrasi. Pemeriksaan visual browser otomatis belum dilakukan.

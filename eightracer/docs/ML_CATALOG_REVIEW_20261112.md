# Penerapan patch, katalog resmi dan evaluasi mandiri ML

Tanggal: 12 November 2026. Semua perubahan kode dipasang di backend utama C:/eightracer/eightracer; pembaruan katalog diterapkan ke PostgreSQL Supabase aktif. Tidak mengubah komponen FE, profil siswa, histori penerimaan PT, atau riwayat penerima beasiswa.

## Patch yang sudah dipasang

Relasi rekomendasiBeasiswaDetail sekarang orderBy(id), sesuai urutan detail yang dibuat saat generate. Regresi API siswa mengaktifkan reverse_unordered_selects di SQLite terisolasi: sebelum patch ranking [2,1] menjadi [1,2]; sesudah patch ranking terjaga. Tes ini terpasang dalam ScholarshipMachineLearningApiTest. Patch lama di folder audit sebelumnya sudah diterapkan melalui kode utama, bukan hanya disimpan sebagai berkas patch.

## Katalog dan sumber

ID/nama lama dipertahankan agar relasi histori tidak diputus. Deskripsi dan kriteria sekarang mengacu pada penawaran yang disebut secara eksplisit dalam official_policy; jadwal tersebut bukan jadwal seluruh periode program.

1. ID 1, Beasiswa Lotte: rujukan SHIN KYUK-HO LOTTE Global Scholarship untuk UB, First Half 2026. Mahasiswa aktif S1/D4, semester 4; alternatif prestasi IPK >=3,20 atau ekonomi IPK >=2,80 dengan bukti SKTM. Tutup 9 April 2026. Surat UB nomor 00327/DST/UN10.A0301/B/KM.01.00/2026 tertanggal 26 Maret 2026: https://feb.ub.ac.id/wp-content/uploads/2026/03/00327_Informasi-Pendaftaran-Beasiswa-SHIN-KYUK-HO-LOTTE-Global-Scholarship-2026.pdf . Bukti ekonomi tidak diisi otomatis. Ketentuan kampus/periode lain dapat berbeda; jangan memakai aturan UB sebagai syarat universal LOTTE.
2. ID 2, beasiswa tanoto: rujukan TELADAN cohort 2027. Rapor kelas XII >=80 pada skala100, semester pertama S1, kampus mitra, beserta ketentuan lain pada sumber. Pendaftaran 1 Juli–7 September 2026. Sumber penyelenggara: https://www.tanotofoundation.org/teladan-2027/ ; pengumuman mitra yang dapat dibaca: https://fasilkom-ti.usu.ac.id/id/berita/pendaftaran-program-beasiswa-teladan-tanoto-foundation-2027-resmi-dibuka . Program ini bukan memakai ambang TKA88.

Sumber penyelenggara Tanoto dan PDF UB kadang menolak fetch langsung (403); isi terindeks serta pengumuman resmi mitra dibandingkan. Jangan menafsirkan tidak dapat fetch sebagai izin membuka login atau mengarang isi. Manifest mencatat URL, konteks penawaran, tanggal verifikasi dan aturan.

Snapshot sebelum perubahan berada privat di .migration-private/catalog-before-official-*.json. Installer scripts/apply-official-scholarship-catalog.php memakai transaksi, lock, pemeriksaan identitas dan versi; menjalankan kembali versi sama tidak menggandakan kriteria. Tidak ada seed siswa atau data penerima baru.

## Perubahan skema dan logika

Migration baru 2026_11_12_133611_add_official_policy_to_basis_data_beasiswa_table menambah JSON nullable official_policy. Migration lama tidak diubah. Isinya provenance, cakupan kampus/jenjang/status, pilihan jalur OR, serta persyaratan yang belum dapat diperiksa otomatis. RLS dan akses backend tetap berlaku pada tabel yang sama.

OfficialScholarshipEligibility digunakan preview/generate dan chat terpilih. Kecocokan teks tidak meloloskan syarat gagal atau tidak diketahui. Jalur LOTTE bukan AND antara ambang ekonomi dan prestasi. Persyaratan manual tetap belum_diketahui; tidak dibuatkan persetujuan palsu. Rapor XII dihitung hanya bila semester5 dan6 tersedia; TKA atau rata-rata semester1–5 tidak menggantikannya. Perubahan katalog melalui CRUD setelah verifikasi menandai review_required sehingga status sumber perlu dicocokkan ulang.

Tidak ada perubahan URL API. Field official_policy pada katalog dan metadata sumber pada evaluasi bersifat tambahan. FE boleh membaca tanggal dan status yang sudah ada. Editor FE saat ini belum mengedit struktur jalur OR; backend menyimpan dan mengevaluasinya di policy.

## Korpus dan jadwal

Korpus referensi sekarang 2 dokumen / 188 fitur TF-IDF. Pemeriksaan ml:beasiswa-check membedakan korpus referensi dari kandidat yang belum tutup. Kedua periode terverifikasi sudah tutup pada tanggal audit; kandidat aktif belum tutup =0. Karena itu preview/generate pendaftaran saat ini tidak boleh menampilkan keduanya sebagai pendaftaran terbuka. Tidak menambahkan jadwal rekaan untuk membuat ML terlihat aktif. Pembukaan periode berikutnya perlu sumber baru.

## Evaluasi mandiri

Dataset tests/Fixtures/ml/source-themed-relevance-v1.json ditulis dan dibekukan sebelum model dijalankan: 4 kasus development,12 held-out,2 safety. Label berasal dari rubrik fokus tema dokumen resmi, bukan jawaban model atau label Guru BK. Identitas dan outcome siswa sekolah tidak digunakan. Kandidat dianggap eligible hanya dalam benchmark pemeringkatan tema; validitas syarat dan jadwal diuji terpisah.

Perintah:

    .\.tools\php84\php.exe scripts\export-ml-ranking-evaluation.php
    python tools/evaluate_scholarship_ranking.py .migration-private/ml-evaluation/held-out.csv --k 1

Hasil tetap tfidf-cosine-v1: baseline Precision@1/NDCG@1=0,50; ranking+fallback=0,833333 (10/12). Sembilan kasus memakai ML, tiga memakai fallback. test11 dan test12 gagal karena bahasa Inggris/parafrasa tanpa overlap; test06 kebetulan benar lewat baseline, bukan kemampuan semantik ML. Dua safety tests (query kosong dan kosakata asing terhadap korpus) memberi fallback yang benar. Tidak menyesuaikan model setelah melihat hasil held-out ini.

Metadata, hash korpus/dataset dan batasan ada dalam docs/ml-evaluation-20261112.json. Ini benchmark tema pada dua program dan query buatan; tidak membuktikan 83,33% akurasi pada siswa nyata, peluang diterima, atau peningkatan kualitas produksi secara umum. Evaluasi mandiri lanjutan memerlukan korpus lebih luas dan set kasus baru yang belum dipakai memilih fitur. Penilaian Guru BK bukan prasyarat menjalankan benchmark mandiri ini.

## Verifikasi

- 15 tests /85 assertions untuk ML dan regresi urutan setelah patch awal.
- 20 tests /105 assertions untuk batas kebijakan resmi, kelengkapan data dan ML sebelum tes jadwal tambahan.
- Suite akhir:222 tests /1874 assertions; tanpa error/failure;1 skip XLSX karena PHP is_readable pada lingkungan Windows terkelola.
- Migration PostgreSQL58 tercatat setelah penambahan kolom; data katalog diperiksa ulang setelah update.
- Pengujian provider memakai respons palsu; tidak ada permintaan Gemini nyata dalam tahap ini.

Deploy/push Git bukan bagian dari verifikasi ini. Integrasi AI nyata diperiksa pada tahap berikutnya.

Pemeriksaan HTTP akhir:22 pemeriksaan endpoint selesai tanpa HTTP500; login kedua role, katalog admin, analitik dan PDF merespons sesuai kontrak. Security advisor Supabase sesudah perubahan: lints kosong; semua63 tabel tetap RLS aktif.

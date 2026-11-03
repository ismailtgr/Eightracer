# Machine learning untuk rekomendasi beasiswa B4

Implementasi awal, 2 November 2026. Lingkup backend; frontend tim FE tidak diubah.

Nilai tambah: rekomendasi beasiswa menggabungkan pemeriksaan syarat resmi, pemeringkatan isi yang dipelajari dari katalog, penjelasan Gemini, dan pemeriksaan admin/BK. Model mengurutkan alternatif yang memenuhi syarat menurut minat siswa yang benar-benar tercatat. Keputusan menerima beasiswa tetap milik penyelenggara.

## Tiga fungsi yang berbeda

| Bagian | Tugas | Bukti yang disimpan |
|---|---|---|
| Evaluator kriteria | Memeriksa jenjang, periode, nilai, ekonomi dan syarat resmi yang dikonfigurasi | Skor kriteria, checklist, data belum diketahui, memenuhi/tidak |
| ML lokal | Content-based recommendation dengan TF-IDF unigram/bigram dan cosine similarity | Versi algoritma, hash korpus, jumlah dokumen, skor kemiripan dan urutan |
| Gemini | Menjelaskan checklist dan langkah persiapan | Model/prompt, alasan dan persiapan yang lolos validasi |

Pembelajaran yang terjadi pada ML: vocabulary dan bobot IDF diturunkan dari frekuensi kata pada deskripsi katalog aktif. Bobot tidak ditetapkan secara manual per siswa atau per beasiswa. Ini metode rekomendasi berbasis isi yang ringan; bukan classifier yang dilatih dari hasil diterima/ditolak. Belum ada dasar untuk mengklaim probabilitas lolos, akurasi tertentu, atau hasil yang lebih baik pada data sekolah.

## Alur

1. Baca profil, katalog aktif yang belum tutup, jenjang target dan program terkait.
2. Evaluator memeriksa seluruh syarat dan menangani data yang belum diketahui.
3. Fit vocabulary/IDF dari `basis_data_beasiswa.deskripsi` sesuai jenjang; maksimum 500 dokumen untuk tahap ini.
4. Bentuk dokumen minat dari nama hobi, cita-cita terstruktur dan `prestasi_murid.bidang` berstatus `terverifikasi`.
5. Hitung cosine similarity di ruang vocabulary yang sama. Syarat yang gagal atau belum lengkap tidak dapat diloloskan ML.
6. Urutkan alternatif eligible menurut kemiripan, lalu skor kriteria dan ID sebagai pemecah seri. Baris lain tetap diagnostik dan `memenuhi=false`.
7. Pratinjau admin berhenti di sini: tidak memanggil Gemini, tidak menulis rekomendasi.
8. Generate meneruskan kandidat/checklist kepada Gemini, tanpa dokumen minat atau metadata ML. Simpan draft dan metadata ML secara atomik pada JSON evaluasi yang sudah ada.
9. Review admin tetap memeriksa kelengkapan dan fingerprint. Siswa hanya membaca hasil miliknya yang disetujui.

Nama murid, NISN, kontak, alamat, agama, jenis kelamin, penghasilan mentah dan catatan impor tidak menjadi fitur ML. Penghasilan hanya dipakai evaluator bila memang merupakan syarat resmi yang dikonfigurasi. Nama kompetisi/pemenang dan lampiran privat tidak diambil sebagai teks minat. Sumber prestasi sekolah tetap disimpan; `pending` berarti menunggu tinjauan aplikasi.

174 catatan peminatan dari impor terakhir belum otomatis menjadi fitur terstruktur. Tanggal dan skor asesmen yang belum diketahui tidak dibuat-buat. Pelengkapan minat dapat memakai endpoint minat yang sudah ada, setelah dicocokkan dengan sumber sekolah.

## Rumus dan versi

Versi `tfidf-cosine-v1` memakai token huruf, lowercase, pembuangan HTML/URL/email, daftar stopword terbatas, unigram dan bigram. Dokumen dibatasi 12.000 karakter dan 256 token awal.

`tf(t,d) = 1 + ln(jumlah kemunculan)`

`idf(t) = ln((1 + jumlah dokumen) / (1 + document frequency)) + 1`

Vektor TF×IDF dinormalisasi L2; dot product vektor query dan katalog menjadi cosine similarity dalam rentang 0–1. Query tidak ikut mempelajari IDF. Fitting dilakukan dalam memori pada request untuk korpus kecil, tanpa service Python terpisah, unggahan dataset atau penyimpanan vektor siswa. Ini baseline lexical; sinonim yang berbeda dapat tidak cocok.

Paling sedikit dua deskripsi bertoken dan dua alternatif eligible diperlukan agar urutan ML dapat dibedakan. Ini batas teknis, bukan bukti kecukupan sampel atau mutu rekomendasi. Bila skor sama/tanpa overlap, sistem memakai baseline. Katalog besar perlu indexing/batch terpisah sebelum batas dinaikkan.

## API untuk FE dan uji backend

`GET /api/siswa/{id}/rekomendasi-beasiswa/preview?target_jenjang=SMA`

- Token administrator atau Guru BK dengan grant aktif wajib; siswa/guru tanpa grant ditolak. `target_jenjang` opsional, hanya SMA atau Kuliah.
- Respons: `data.target_jenjang`, `data.pemeringkatan_ml`, `data.kandidat` maksimal 20.
- Preview tidak membutuhkan API key Gemini dan tidak menciptakan draft.
- Katalog/jenjang kosong: 422; tidak menghasilkan daftar beasiswa buatan.
- `status=applied` menunjukkan ranking dipakai pada request, bukan bukti akurasi atau prediksi diterima.
- `skor_kecocokan` tetap skor kriteria 0–100. `pemeringkatan_ml.skor_kemiripan` terpisah, 0–1; jangan diberi label “peluang lolos”.

Contoh bentuk metadata; nilai berikut hanya ilustrasi:

```json
{"metode":"tfidf-cosine-v1","status":"applied","skor_kemiripan":0.72,"peringkat":1}
```

`POST /api/siswa/{id}/rekomendasi-beasiswa/generate` sekarang memakai hasil ranking lokal sebelum penjelasan Gemini. Endpoint review tetap sama. `GET /api/rekomendasi-beasiswa/saya` menambahkan metadata ML terbatas; field lama dipertahankan. FE boleh mengabaikan field baru sampai komponen tampilannya siap. Metadata internal/hash hanya untuk admin dan audit backend.

## Kondisi fallback

| alasan_status | Arti |
|---|---|
| disabled | `SCHOLARSHIP_ML_ENABLED=false` |
| catalog_insufficient | Kurang dari dua deskripsi katalog yang bisa dipelajari |
| catalog_limit | Lebih dari 500 kandidat; ranking isi tidak dijalankan |
| profile_insufficient | Tidak ada sumber minat terstruktur |
| no_eligible_alternatives | Belum ada dua pilihan yang memenuhi syarat |
| no_eligible_overlap | Kosakata minat tidak cocok dengan alternatif eligible |
| no_discriminative_signal | Kemiripan semua alternatif eligible sama |
| criteria_incomplete / criteria_not_met | Data syarat belum lengkap / gagal; kemiripan tidak dipakai |

Fallback tidak mengarang similarity. Nilainya `null`. Hasil eligible diprioritaskan, lalu skor kriteria dan ID. Tidak diperlukan migration, akun baru, atau perubahan data sekolah. Hash sumber kini mencakup sumber minat dan versi/konfigurasi ML: draft pending lama perlu generate ulang sebelum disetujui. Hasil historis yang sudah disetujui tidak ditulis ulang.

## Kesiapan data dan pengujian manfaat

Jalankan `php artisan ml:beasiswa-check --json` menggunakan PHP proyek yang sesuai. Perintah hanya membaca agregat; tidak menghubungi Gemini atau menulis data. Laporan kesiapan bukan laporan akurasi. Pada snapshot sebelumnya, katalog, kriteria dan catatan beasiswa progres alumni belum terisi; jangan menganggap data penerimaan PT sebagai label hasil beasiswa.

Untuk menguji manfaat sesungguhnya:

1. Isi katalog resmi dengan deskripsi yang menjelaskan bidang/sasaran serta syarat terstruktur. Catat sumber dan masa berlaku.
2. Lengkapi minat dan bidang prestasi terverifikasi. Data yang belum diketahui tetap kosong.
3. BK memberi penilaian relevansi pasangan profil–beasiswa: 0 tidak relevan, 1 lemah, 2 relevan, 3 sangat relevan. Gunakan ID profil pseudonim pada dataset evaluasi, tanpa nama/NISN/kontak.
4. Simpan urutan baseline kriteria dan urutan ML untuk kandidat yang sama. Gunakan `docs/ml-evaluation-template.csv`; semua kandidat pada template evaluasi sudah eligible.
5. Pisahkan profil untuk pengembangan dan penilaian akhir; versi siswa yang sama tidak masuk dua kelompok. Bila memakai histori, fitur harus berasal dari waktu rekomendasi, tanpa informasi hasil yang baru muncul kemudian.
6. Jalankan `python tools/evaluate_scholarship_ranking.py docs/ml-evaluation-template.csv --k 5`. Tanpa label, hasilnya `awaiting_labels`; tidak ada skor mutu palsu. Laporan membandingkan Precision@5 dan NDCG@5. Uji kode sintetis hanya membuktikan fungsi, bukan mutu pada murid sekolah.
7. Tinjau hasil per kelompok data dan kasus meleset bersama BK. Aktifkan klaim peningkatan mutu hanya setelah evaluasi terpisah mendukungnya. Model supervised dapat dipertimbangkan setelah hasil dan keputusan relevansi yang cukup serta representatif terkumpul.

## Berkas implementasi

- `TfidfScholarshipModel`: fitting dan cosine similarity.
- `ScholarshipInterestProfile`: allowlist sumber minat.
- `ScholarshipContentRanker`: pengurutan dengan guard kriteria dan fallback.
- `ScholarshipRecommendationService`: preview, integrasi generate dan fingerprint.
- `ScholarshipMlReadiness`: pemeriksaan agregat.
- Pengujian ML unit/API dan evaluator metrik menyertakan fixture sintetis terisolasi; tidak mengimpor data latihan palsu ke MySQL sekolah.

Rujukan metode: [Google — content-based filtering](https://developers.google.com/machine-learning/recommendation/content-based/basics), [scikit-learn — TF-IDF](https://scikit-learn.org/stable/modules/generated/sklearn.feature_extraction.text.TfidfVectorizer.html), [scikit-learn — NDCG](https://scikit-learn.org/stable/modules/generated/sklearn.metrics.ndcg_score.html), [pencegahan data leakage](https://scikit-learn.org/stable/common_pitfalls.html).

# Backend rekomendasi beasiswa

Bagian ini berisi layanan API Laravel untuk FE yang dikembangkan terpisah.

- Kontrak integrasi, role, body request, pagination, respons/error, CORS:
  [integrasi-fe-beasiswa.md](integrasi-fe-beasiswa.md).
- Contoh request HTTP: [beasiswa-api.http](beasiswa-api.http).
- Engine: `app/Services/Beasiswa/EligibilityEvaluator.php` (pemeriksaan dan skor),
  `GeminiScholarshipExplainer.php` (penjelasan Gemini),
  `ScholarshipRecommendationService.php` (draft/snapshot).
- Konfigurasi: `config/gemini.php`; key hanya di `.env` yang diabaikan Git.

Runtime project: `.tools/php84/php.exe`, jalankan
`./php-project.ps1 artisan serve --host=127.0.0.1 --port=8000` dari root aplikasi.
Base URL FE: `http://127.0.0.1:8000/api`.

Status penerapan:

- Migration metadata sudah diterapkan di MySQL lokal `eightracer_db`.
- Key Gemini sudah berada di `.env`; kedua file contoh tidak mengandung key.
- Provider memakai `gemini-3.1-flash-lite` melalui Interactions API, store=false.
- Uji koneksi dengan beasiswa sintetis sukses; JSON hasil lolos validasi.
- Tes backend terfokus: 14 tes, 55 assertion; database SQLite in-memory dan HTTP fake.
- UI buatan agen sudah dihapus. Integrasi hanya melalui endpoint API untuk FE tim.

Skor bukan probabilitas penerimaan. Status data yang belum diketahui tidak lolos
publikasi. Admin harus meninjau draft sebelum hasil tersedia untuk siswa.
Durasi/periode perkiraan katalog, rekomendasi kuliah/alumni, dan seluruh PRD auth
belum dicakup tahap implementasi ini; lihat batasan pada kontrak API.

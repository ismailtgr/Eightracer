# Verified recommendations — 14 November 2026

The main catalog is synced using `php artisan scholarships:sync-verified --apply` after its default dry run. It validates five exact program identities, backs up catalog rows privately, and updates descriptions plus official-policy references only. It does not change alumni, awards, archive flags, registration dates or saved numeric criteria. No migration is required.

Source pack: `database/data/scholarships/verified-20261114.php`. KIP Kuliah uses the official 2026 FAQ; Bebas IPI and school-labelled Prestasi Basket use UM's SM Prestasi source. TELADAN here is UIN Malang's program, not Tanoto. The UPH nursing brochure verifies program benefits only: it does not establish current application dates or individual alumni contracts. Unknown requirements remain unknown. OR alternatives, including KIP economic paths and UM award/English paths, must not become simultaneous mandatory conditions.

Flow: authenticated own student profile -> active non-expired catalog -> official criteria evaluation -> content relevance -> explanation -> personal result. Fully eligible candidates remain ahead of exploratory candidates. Where at least two incomplete candidates have no known failed requirements, TF-IDF may order them by relevance; `alasan_status=exploration_only` never changes `memenuhi`, `lengkap`, criterion score or checklist. Insufficient interests or vocabulary uses a deterministic fallback. No BK approval is needed to read personal results. Archived TELADAN remains archived.

Evaluation: `php artisan scholarships:evaluate --live-ai`. One real Gemini request uses three non-personal diagnostic cases. Private report: `storage/app/private/scholarship-evaluation/latest.json`. It contains no student identities or credentials.

Results of the initial diagnostic: 10/10 top-1 matches, MRR 1.0, coverage 1.0; fixed ID-order baseline top-1 0.20 and MRR 0.4567. Precision@3 is 0.3333 because each case labels one relevant program. Fifteen incomplete-profile safety cases produce zero false eligible decisions. Three Gemini responses pass uncertainty/no-guarantee heuristics.

Limitations: these are ten manually written keyword-oriented cases against five curated source summaries, including archived TELADAN solely in the offline benchmark. They are not independent population data or acceptance outcomes. 100% on this tiny diagnostic is not 100% real-world accuracy. The AI checks are heuristics, not a complete factual correctness assessment. Do not advertise prediction accuracy, calibrated probabilities or successful supervised training. Expand to independently labelled paraphrases, ambiguous requests and a held-out evaluation before making broader quality claims.

## Optional staff snapshot persistence (2026-11-14)

`POST /api/siswa/{siswa}/rekomendasi-beasiswa/save` accepts only optional `target_jenjang` (`SMA` or `Kuliah`). School staff only. Returns HTTP 200 with the saved recommendation relation and `meta: {automatic: true, requires_review: false, saved: true}`.

The service recomputes the current rule/ML preview without invoking Gemini, locks concurrent generation/saving, and checks the source fingerprint before commit. The latest snapshot with unchanged sources is reused, preserving optional staff edits. Changed sources create a new history snapshot. Unknown criteria remain unknown. The existing internal review column is retained for compatibility; it does not gate student automatic results. No schema migration is required.

UI uses Simpan for persistence and keeps Generate Ulang separate for provider regeneration. No anonymous demo publication flow remains in the admin page. Student automatic recommendations remain independent of staff save/review actions.

Validation: isolated in-memory SQLite suite including save authorization, untrusted fields, repeat-save, source changes, unknown criteria, and concurrent generation locking. No school rows are mutated by these tests.

## Gaya percakapan siswa (14 November 2026)

Chat menyapa nama depan siswa dari sesi lokal, dengan emoji secukupnya. Nama tidak ditambahkan ke payload Gemini. Prompt penjelasan memakai bahasa Indonesia hangat dan suportif, dengan langkah persiapan terpisah. Server tetap menyusun fakta chat dari data/checklist terverifikasi: judul tebal, persyaratan per poin, langkah berikutnya per poin, dan status belum diketahui tetap utuh. Perubahan ini adalah pengaturan prompt dan format respons, bukan supervised retraining model atau perubahan skor ML.

FE merender hanya paragraf, daftar, dan penekanan tebal; tidak mengeksekusi HTML atau tautan model. Pratinjau tetap mengungkapkan bahwa jawabannya contoh. Cache penjelasan otomatis memakai versi baru agar gaya lama tidak digunakan kembali. Data siswa/alumni, syarat resmi, dan hasil kelayakan tidak diubah.

# Pencocokan use case dan rencana pengujian Eightracer

Tanggal audit: 11 November 2026. Sumber: dokumen use case scenario yang dikirim pengguna (UC01–UC27), kode backend/FE lokal, rute API dan laporan pengujian otomatis terbaru. Isi lampiran dipakai sebagai kebutuhan pembanding, bukan instruksi untuk mengubah kode secara otomatis.

## Kesimpulan

Belum 100% sesuai. Alur data sekolah, asesmen, alumni, katalog, evaluasi, review dan privasi banyak didukung backend. Ada selisih kontrak dan kebijakan pada UC01–UC04, UC07–UC08, UC14 dan UC24. Dokumen juga belum mencakup seluruh pembaruan B4. Satu UC dapat sudah didukung BE tetapi belum terbukti lulus dari UI.

Bukti tes terakhir yang tersedia: suite 185 tes, 184 lulus, satu XLSX dilewati, 1569 assertions; setelah penambahan pengecekan perubahan media sosial, tes CRUD terarah 3 tes/114 assertions lulus. Ini hasil audit CRUD sebelumnya, bukan bukti semua skenario lampiran sudah dijalankan lewat browser. Hasil manual pada CSV sengaja kosong.

## Selisih yang harus diselesaikan sebelum UAT dinyatakan selesai

1. UC02: ganti endpoint usulan menjadi POST /api/akun-siswa/provision. Payload aktual {"siswa_ids":[ID],"dry_run":true/false}, maksimal100 ID. Tidak ada unggah Excel akun langsung; identitas invalid pada level validasi menolak request422. Profil yang sudah berakun ditahan tanpa mengganti password.
2. UC03 dan UC24: hapus pernyataan login admin baru otomatis mencabut sesi lama jika memakai kebijakan terbaru yang diminta pengguna. Kode mempertahankan sesi per perangkat; logout mencabut perangkat aktif; ganti/reset password mencabut seluruh sesi. Batas72/168jam tetap sesuai.
3. UC03: dokumen membatasi siswa hanya NISN/password dan Google hanya admin, tetapi kode login email dan Google belum membatasi role tersebut secara eksplisit. Catat GAP kebijakan; jangan menganggap provider503 sebagai bukti pembatasan role.
4. UC04: reset password siswa oleh admin masih usulan dan route belum tersedia. Forgot/reset OTP juga belum dibatasi eksplisit ke role administrator. Perlu implementasi atau persetujuan perubahan requirement sebelum menandai lulus.
5. UC07/UC14: POST siswa manual mewajibkan NISN10digit. Dokumen mengizinkan profil alumni tanpa NISN; perlu membedakan jalur impor terkurasi dari form manual.
6. UC08 dan aktor: guru BK dengan grant aktif dapat memperoleh hak staff; guru biasa tidak. Dokumen perlu menjelaskan perbedaannya.
7. UC01: Navbar yang diimpor layout aktif masih mempunyai href /register. Periksa kondisi render di browser dan hilangkan tautan aktif bila memang muncul. FAQ publik BE ada; sambungan FAQ landing belum terbukti dari pemeriksaan ini.
8. UC20/UC21: tambahkan preview, TF-IDF/cosine, edit alasan/waktu, hide/show, soft delete, revision dan penarikan publikasi setelah perubahan. Siswa hanya mendapat batch terbaru per jenjang yang disetujui, aktif dan tidak tersembunyi.
9. Tambahkan cakupan chat beasiswa, dashboard UKT/IPK, buku PDF, impor preview/commit serta analitik sumber sekolah. Nomor UC28/UC29 pada CSV hanya usulan, belum penomoran resmi.

## Matriks UC01–UC27

| UC | Aktor | Penilaian | Catatan | Bukti kode/tes |
|---|---|---|---|---|
| UC01 | Pengunjung | Parsial FE | FAQ tersedia di BE; Navbar aktif masih memuat href /register. Pemanggilan FAQ BE pada landing belum terbukti. | DashboardController::faq; frontend/app/layout.tsx; components/shared/Navbar.tsx |
| UC02 | Admin | Parsial kontrak | Endpoint aktual POST /akun-siswa/provision dengan siswa_ids dan dry_run; dua endpoint usulan dokumen belum ada. Unggah file akun langsung belum tersedia; ID tak dikenal menolak seluruh request 422. | SchoolAccountController; SchoolAccountProvisioner; SchoolProvisionedAccountsTest |
| UC03 | Admin/Siswa | Beda kebijakan | Login admin mempertahankan perangkat lain (perubahan yang diminta sebelumnya). Login email tidak membatasi role siswa; Google belum dibatasi eksplisit hanya admin. | SessionIssuer; SchoolAuthController::login; GoogleAuthController::login; BackendSecurityTest |
| UC04 | Admin | Belum lengkap | PATCH /siswa/{id}/reset-password belum ada. Forgot/reset OTP tidak membatasi role administrator secara eksplisit. | routes/api.php; routes/prd.php; SchoolAuthController::forgot/reset |
| UC05 | Admin/Siswa | Sesuai BE | Password sendiri menggunakan current_password; OTP untuk akun tanpa password. Perlu UAT unggahan/scanner. | ProfileController; PrdAuthenticationTest |
| UC06 | Admin/Siswa | Sesuai BE | Ringkasan dibatasi per peran; akun siswa harus tertaut. | DashboardController; UseCaseAcceptanceAuditTest |
| UC07 | Admin | Parsial validasi | POST siswa manual mewajibkan NISN 10 digit; dokumen menyebut unik bila diisi. Impor alumni merupakan jalur berbeda. | SiswaController::store; StudentEconomyController; CrudCoverageTest |
| UC08 | Admin | Sesuai dengan catatan role | Filter tersedia. Guru BK berizin dapat menjadi staff; larangan semua guru pada dokumen terlalu umum. | SiswaController::index/show; SchoolAccess; RecommendationStudentFilterTest |
| UC09 | Admin | Sesuai BE | Koreksi asesmen menjaga histori dan tanggal aktual. | AssessmentController; PrdSchoolModulesTest |
| UC10 | Admin/Siswa | Sesuai BE | Endpoint histori admin dan /saya terpisah. | AssessmentController::history/mine; PrdSchoolModulesTest |
| UC11 | Admin | Sesuai BE | CRUD dan batas pemilik diuji; pemindai nyata tetap perlu UAT. | StudentSupportController; CrudCoverageTest; BackendSecurityTest |
| UC12 | Admin | Sesuai BE | Versi program dan fingerprint sumber tersedia. | SchoolEligibility; PrdSchoolModulesTest |
| UC13 | Admin/Siswa | Sesuai BE | Proyeksi siswa menyembunyikan ambang internal. | SchoolEligibilityController; UseCaseAcceptanceAuditTest |
| UC14 | Admin | Parsial jalur input | Penerimaan dan izin tersedia; pembuatan profil tanpa NISN melalui POST siswa manual belum didukung. | StudyTracerController; SchoolRecordWriter; UseCaseAcceptanceAuditTest |
| UC15 | Admin | Sesuai BE | Progres memiliki periode, revisi, arsip, izin finansial terpisah. | AlumniService; AlumniProgressEditorTest |
| UC16 | Admin/Siswa | Sesuai BE; UAT FE | Filter BE lengkap; filter Jejak Alumni FE saat ini berlaku pada halaman aktif. | StudyTracerController::studentIndex/studentShow; PrdAlumniPrivacyTest |
| UC17 | Admin/Siswa | Sesuai BE | Timeline mempertahankan presisi tahun/tanggal sumber. | AlumniTimelineTest; UseCaseAcceptanceAuditTest |
| UC18 | Admin | Sesuai BE; perlu perluasan dokumen | Analitik histori dan pola tersedia; endpoint analitik sumber sekolah/PDF tambahan belum dicantumkan dokumen. | Analytics; SchoolScholarshipAnalyticsTest; UseCaseAcceptanceAuditTest |
| UC19 | Admin | Sesuai BE; perlu perluasan dokumen | Status program dan pendaftaran terpisah; detail GET /beasiswa/{id} juga tersedia. | BasisDataBeasiswaController; ScholarshipApiContractTest; RecommendationCrudTest |
| UC20 | Admin | Sesuai BE; perlu perluasan dokumen | Tambah TF-IDF/cosine, preview, CRUD detail, revision, hide dan penarikan publikasi setelah edit ke skenario. | ScholarshipRecommendationService; RecommendationCrudTest; ScholarshipMachineLearningApiTest |
| UC21 | Siswa | Sesuai BE; perlu perluasan dokumen | Hanya batch terbaru per jenjang. Batch terbaru pending menarik hasil lama; hidden/deleted/inactive tidak tampil. | RekomendasiBeasiswaController::mine; RecommendationCrudTest |
| UC22 | Admin | Sesuai BE | Bobot dan fingerprint jurusan diuji terpisah dari beasiswa. | MajorRecommendation; PrdSchoolModulesTest |
| UC23 | Siswa | Sesuai BE; UAT FE | Proyeksi hasil jurusan sendiri telah diuji BE. | MajorRecommendationController::mine; PrdSchoolModulesTest |
| UC24 | Admin/Siswa | Beda kebijakan dokumen | Logout hanya perangkat aktif sudah sesuai; catatan login admin baru mencabut semua sesi sudah kedaluwarsa. | AuthController::logout; BackendSecurityTest |
| UC25 | Siswa | Sesuai BE | Agregat publik memeriksa izin anggota dan batas kelompok. | Analytics::published; PrdAlumniPrivacyTest |
| UC26 | Admin | Sesuai konsep; perlu perluasan dokumen | Selain impor terkurasi, sudah ada /imports preview/commit dan progres impor; jangan samakan format unggah dengan rekonsiliasi semua tab. | BulkImportController; B4WorkflowTest; AlumniProgressEditorTest |
| UC27 | Admin | Sesuai BE | Enam jenis referensi telah diuji CRUD penuh. | ReferenceController; CrudCoverageTest |

## Cara menggunakan lembar test case

CSV berisi 64 kasus: dua skenario per UC (positif dan negatif/alternatif), ditambah10 pemeriksaan kontrak/regresi. Buka di Excel/Google Sheets, isi Status_Eksekusi_Manual dengan Pass, Fail, Blocked, atau Not Run; tuliskan Hasil_Aktual dan tautan bukti. Status kesesuaian kode tidak sama dengan hasil eksekusi manual.

1. Gunakan database uji dan akun berbeda untuk admin, siswa A, siswa B, guru biasa dan BK berizin bila cakupannya disetujui.
2. Provision akun siswa melalui endpoint aktual, bukan memilih profil sekolah secara acak. Password/token tidak dimasukkan ke laporan.
3. Semua path dokumen berada di bawah prefix /api pada backend. FE dapat memakai BFF /api/b4; jangan menukar URL FE dan BE.
4. Eksekusi skenario positif dahulu, lalu batas nilai, data kosong, izin akses, konflik/duplikat, dan sinkronisasi.
5. Catat respons HTTP, hasil database, dan UI secara terpisah. Respons200 saja belum membuktikan perubahan tersimpan; baca ulang data.
6. Untuk dependensi Gemini, Google, email dan antivirus, pisahkan tes provider palsu otomatis dari tes integrasi nyata.
7. Jangan mencatat Pass pada skenario personal jika akun belum tertaut; tandai Blocked—prasyarat data.
8. Referensi persetujuan publikasi sekolah belum dituliskan pengguna. Pernyataan “sudah ada” bukan bukti bahwa field referensi sudah terisi. Jangan mengarang catatan persetujuan.

## Skenario UAT utama B4

A. Admin tambah katalog valid -> baca detail -> edit -> arsip -> aktifkan. Verifikasi status program terpisah dari status pendaftaran; nama bulan tanpa tahun tidak dipakai untuk menyimpulkan buka/tutup.

B. Admin catat penerimaan alumni -> tambah progres semester dengan IPK/UKT/beasiswa yang benar -> revisi -> publikasikan penerimaan dan progres dengan referensi izin. Siswa membaca Jejak Alumni; nominal hanya muncul jika izin finansial aktif. Cabut publikasi dan pastikan tautan detail lama tidak lagi membuka data.

C. Admin pilih siswa uji lengkap -> preview target jenjang -> generate -> cek checklist/skor -> edit alasan/waktu -> publikasi. Siswa terkait membaca hasil, siswa B tidak dapat membacanya. Admin mengubah hasil; hasil siswa ditarik sampai review ulang. Coba revision lama:409. Sembunyikan/hapus kandidat dan pastikan tidak muncul setelah publikasi ulang.

D. Ulangi C dengan TKA/data kriteria kosong: hasil tetap belum diketahui dan publikasi ditolak422. Jangan mengisi nilai contoh di database sekolah untuk membuat tes tampak lulus.

E. Login A dan B memakai admin yang sama: keduanya tetap masuk menurut kebijakan aktual. Logout A hanya mematikan A; ganti password mencabut semuanya. Dokumen lama yang mengharapkan B mengeluarkan A perlu diperbarui.

## Data yang masih menghambat UAT nyata

Audit data terakhir pada11November2026:872 profil,599 penerimaan,0 profil tertaut akun,0 penerimaan ditandai publikasi,2 program katalog dan0 batch rekomendasi. Ini snapshot audit terdahulu pada hari yang sama; periksa ulang sebelum pelaksanaan UAT. Akun uji siswa tanpa profil memperoleh404 pada rekomendasi personal. Nilai TKA belum tercatat pada audit B4 sebelumnya sehingga pengujian publikasi positif memerlukan data uji terisolasi yang lengkap.

Dokumen ini tidak mengubah kode, UI, akun, maupun izin publikasi. Temuan harus dipisahkan menjadi revisi dokumen (misalnya multi-perangkat yang memang diminta), kekurangan implementasi (reset siswa), dan prasyarat uji (akun/data/provider).

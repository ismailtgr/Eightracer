# Skenario use case Eightracer

Revisi 4 November 2026. Alur akun siswa mengikuti keputusan sekolah: akun disiapkan admin, tanpa registrasi publik. Seluruh use case berada pada satu diagram. Skenario mengikuti PRD, sidebar, backend yang tersedia, serta struktur database saat diperiksa. Ini bukan pernyataan bahwa frontend dan konfigurasi layanan live telah selesai.

Data prestasi berasal dari sekolah. `pending` adalah status menunggu pemeriksaan pada aplikasi; bukan penilaian atas keabsahan sekolah. Kelengkapan baris dan kemungkinan pengulangan ditangani sambil mempertahankan sumber.

## UC01 — Lihat halaman publik dan FAQ

**Aktor:** Pengunjung

**Prasyarat:** Tidak perlu login.

**Alur utama:**

1. Pengunjung membuka halaman informasi aplikasi di frontend.
2. Frontend mengambil FAQ publik dari backend.
3. Pengunjung memilih login untuk fitur terlindungi; akun siswa disiapkan admin sekolah.

**Alternatif dan validasi:** Landing page milik FE; FAQ tidak membuka data murid. Kegagalan layanan menampilkan pesan umum.

**Hasil:** Informasi publik tersedia tanpa biodata sekolah.

**API/alur:** GET /faq

**Tabel:** tidak memakai data murid

**Rujukan implementasi:** PRD FAQ (bagian registrasi digantikan keputusan sekolah); DashboardController::faq

## UC02 — Siapkan akun siswa oleh admin

**Aktor:** Admin sekolah

**Prasyarat:** Admin aktif dan berwenang; master siswa sudah tersedia melalui input atau impor sekolah.

**Alur utama:**

1. Admin memilih ID siswa dari master, lalu mengirim pratinjau provisioning.
2. Backend memeriksa NISN 10 digit yang unik, identitas dan akun yang sudah terhubung.
3. Admin melaksanakan pembuatan akun untuk baris yang siap.
4. Backend membuat username NISN dan password awal acak berbeda per siswa; menyimpan hash, jejak admin, serta relasi siswa.user_id dalam transaksi.
5. Respons rahasia sekali tampil diterima admin dan disampaikan kepada siswa secara pribadi.
6. Siswa menggunakan akun tersebut untuk login (UC03), lalu dapat mengganti password sendiri melalui profil (UC05).

**Alternatif dan validasi:** Hanya administrator boleh membuat akun. NISN kosong/tidak valid, identitas bertentangan, nama kosong, atau akun yang sudah ada ditahan dan diberi alasan. Pratinjau tidak membuat akun. Pengulangan tidak mereset password lama. Tidak membuat NISN/email palsu. Email tidak wajib untuk akun sekolah dan tidak otomatis ditandai terverifikasi. Register publik dan klaim master melalui Google ditutup.

**Hasil:** Akun role siswa aktif berdasarkan provisioning sekolah, terhubung ke satu master siswa; password tersimpan hanya sebagai hash.

**API/alur:** POST /akun-siswa/provision (dry_run=true, lalu false); POST /login (NISN/password); PUT /profile/password (password lama/baru).

**Tabel:** `users`, `siswa`, `personal_access_tokens`

**Rujukan implementasi:** SchoolAccountController; SchoolAccountProvisioner; SchoolAccess::canAuthenticate; SessionIssuer; ProfileController::password

## UC03 — Login dan kelola sesi sesuai peran

**Aktor:** Admin sekolah, Siswa

**Prasyarat:** Akun tersedia. Siswa diaktifkan melalui provisioning sekolah atau akun lama yang sudah terverifikasi; admin/staf mengikuti verifikasi email dan izin staf.

**Alur utama:**

1. Siswa mengirim NISN/password awal atau password yang sudah diubah; admin mengirim email/password. Google hanya untuk akun yang sudah tersedia dan memenuhi bukti kepemilikan email.
2. Backend memeriksa kredensial/nonce serta pembatasan percobaan.
3. SessionIssuer mengunci akun dan membuat token baru khusus perangkat ini; sesi perangkat lain tetap aktif.
4. Terbitkan token admin maksimal 72 jam atau siswa 168 jam; tampilkan profil sesuai role.

**Alternatif dan validasi:** Kredensial salah menggunakan pesan umum. Token tanpa expiry atau kedaluwarsa ditolak. Aktivasi siswa oleh sekolah terpisah dari verifikasi email. Akun admin yang sama dapat dipakai beberapa perangkat bersamaan; masa berlaku setiap token tetap 72 jam dan login baru tidak memperpanjang token lama. Login Google hanya memberi hak sesuai akun server; role guru lama tidak mendapat akses admin.

**Hasil:** Token server berlaku sesuai kebijakan peran.

**API/alur:** POST /login; /auth/google; GET /me

**Tabel:** `users`, `personal_access_tokens`, `sessions`, `auth_challenges`

**Rujukan implementasi:** SessionIssuer::issue/revoke; token.lifetime; GoogleIdentityVerifier::verify

## UC04 — Pulihkan password melalui OTP

**Aktor:** Admin sekolah, Siswa

**Prasyarat:** Email akun terverifikasi dan layanan OTP tersedia; batas permintaan belum terlampaui. Siswa tanpa email meminta bantuan admin, tanpa bypass pemulihan publik.

**Alur utama:**

1. Minta reset melalui email; respons awal tetap umum meski akun tidak ditemukan.
2. Kirim OTP sekali pakai, masa berlaku 10 menit dan maksimal lima kesalahan.
3. Verifikasi OTP menjadi reset grant sekali pakai selama lima menit.
4. Validasi dan hash password baru; konsumsi grant serta cabut seluruh token/sesi lama.

**Alternatif dan validasi:** Permintaan dibatasi per email/IP. OTP/grant salah, habis waktu atau terpakai ditolak. Email gagal tidak mengubah password.

**Hasil:** Password baru tersimpan dan sesi lama tidak dapat dipakai.

**API/alur:** POST /forgot-password; /forgot-password/verify; /reset-password

**Tabel:** `users`, `auth_challenges`, `personal_access_tokens`, `sessions`

**Rujukan implementasi:** SchoolAuthController; OtpService; SessionIssuer

## UC05 — Kelola profil dan ganti password sendiri

**Aktor:** Admin sekolah, Siswa

**Prasyarat:** Token sah dan hanya akun pemilik token. Siswa dengan password awal sekolah dapat menggantinya menggunakan password lama; tidak wajib OTP email untuk jalur ini.

**Alur utama:**

1. Lihat profil akun sendiri; ubah full_name atau minta perubahan email/password.
2. Perubahan sensitif memakai password saat ini atau OTP sesuai jenis akun.
3. Foto divalidasi ukuran/tipe/piksel, dipindai dan ditulis ulang sebagai PNG.
4. Simpan perubahan; email berubah melepaskan Google lama dan membatalkan sesi.

**Alternatif dan validasi:** Role/user_id tidak dapat diubah dari profil. File gagal pindai atau pemindai tidak tersedia ditolak. Biodata master sekolah tidak ditimpa nama akun.

**Hasil:** Profil akun berubah dalam batas pemiliknya.

**API/alur:** GET/PATCH /profile; /profile/email/*; /profile/password*; /profile/photo; POST /profile/google

**Tabel:** `users`, `auth_challenges`, `private_attachments`, `personal_access_tokens`, `sessions`

**Rujukan implementasi:** ProfileController; AttachmentController; ClamAvScanner; GoogleAuthController::link

## UC06 — Lihat beranda sesuai peran

**Aktor:** Admin sekolah, Siswa

**Prasyarat:** Token aktif.

**Alur utama:**

1. Backend menentukan peran dari token.
2. Admin menerima ringkasan agregat sekolah.
3. Siswa menerima ringkasan dan pintasan milik profilnya sendiri.

**Alternatif dan validasi:** Hasil yang belum ada ditampilkan sebagai belum tersedia; tidak diisi skor atau data siswa lain.

**Hasil:** Beranda sesuai kewenangan.

**API/alur:** GET /beranda

**Tabel:** `siswa`, `hasil_program_eligibilitas`, `asesmen_karakteristik`, `study_tracer`, `alumni_progress`

**Rujukan implementasi:** DashboardController::index

## UC07 — Kelola biodata, keluarga dan ekonomi

**Aktor:** Admin sekolah

**Prasyarat:** Administrator aktif.

**Alur utama:**

1. Tambah atau pilih master siswa; isi biodata tanpa mewajibkan akun login.
2. Isi ayah/ibu, kondisi dan penghasilan; wali diperlukan sesuai kondisi orang tua.
3. Simpan bantuan aktif, minat dan kontak sesuai relasi pemiliknya.
4. Perubahan kelas memakai tanggal nyata dan menutup histori sebelumnya; operasi ekonomi disimpan dalam transaksi.

**Alternatif dan validasi:** NISN unik bila diisi. Penghasilan kosong tetap tidak diketahui. Data impor tanpa tanggal/tahun ajaran tetap pada catatan sumber; tidak membuat histori kelas dengan tanggal perkiraan.

**Hasil:** Profil dan keluarga konsisten serta data asal dipertahankan.

**API/alur:** /siswa; PUT /siswa/{id}/ekonomi; POST /siswa/{id}/kelas; PUT /siswa/{id}/minat

**Tabel:** `siswa`, `orang_tua`, `wali`, `bantuan_siswa`, `media_sosial`, `saudara_tiri`, `hobi_siswa`, `cita_cita_siswa`, `riwayat_kelas`

**Rujukan implementasi:** SiswaController; StudentEconomyController; StudentSupportController

## UC08 — Cari dan lihat detail master siswa

**Aktor:** Admin sekolah

**Prasyarat:** Administrator aktif.

**Alur utama:**

1. Cari nama/NISN atau gunakan filter kelas, tahun lulus dan jenis kelamin.
2. Backend mengembalikan daftar berpaginasi dalam lingkup admin.
3. Buka detail termasuk catatan sumber untuk melihat kelengkapan data.

**Alternatif dan validasi:** Hasil kosong bukan kegagalan. Siswa/guru tidak boleh membuka modul master admin. Tahun filter adalah tahun lulus, bukan tahun ajaran sumber.

**Hasil:** Admin memperoleh profil sekolah yang dipilih.

**API/alur:** GET /siswa; GET /siswa/{id}

**Tabel:** `siswa`, `orang_tua`, `wali`, `riwayat_kelas`

**Rujukan implementasi:** SiswaController::index/show

## UC09 — Catat dan koreksi asesmen bertanggal

**Aktor:** Admin sekolah

**Prasyarat:** Murid teridentifikasi; tanggal tes diketahui dan data yang diisi bersumber dari sekolah.

**Alur utama:**

1. Pilih siswa serta tanggal pelaksanaan tes; isi kategori, gaya belajar dan IQ bila tersedia.
2. Bila memasukkan RIASEC angka, isi enam skor; MI memakai pasangan jenis dan skor asli.
3. Koreksi mengacu asesmen lama; simpan entri baru dan relasi detail dalam transaksi.
4. Tentukan entri current berdasarkan tanggal/versi tanpa menghapus histori.

**Alternatif dan validasi:** Tanpa tanggal, simpan sebagai catatan sumber melalui UC26; belum menjadi asesmen formal. Label MI/kode RIASEC tidak diubah menjadi skor buatan. Kategori campuran perlu konfirmasi sebelum dipaksakan ke kategori tunggal.

**Hasil:** Histori asesmen formal dan versi current konsisten.

**API/alur:** POST /siswa/{id}/asesmen

**Tabel:** `siswa`, `asesmen_karakteristik`, `asesmen_riasec`, `asesmen_multiple_intelligence`

**Rujukan implementasi:** AssessmentController::store; tanggal_asesmen NOT NULL

## UC10 — Lihat riwayat asesmen sendiri/terpilih

**Aktor:** Admin sekolah, Siswa

**Prasyarat:** Admin memilih siswa; siswa dibatasi relasi akun sendiri.

**Alur utama:**

1. Admin meminta histori siswa terpilih; siswa memakai endpoint /saya.
2. Urutkan karakteristik, RIASEC dan MI berdasarkan tanggal.
3. Tampilkan data formal yang tersedia; catatan peminatan mentah tetap dibedakan.

**Alternatif dan validasi:** Riwayat kosong berarti belum ada tes formal yang dicatat. Permintaan identitas lain tidak diberikan kepada siswa.

**Hasil:** Hasil tes tampil sesuai lingkup akses.

**API/alur:** GET /siswa/{id}/asesmen; GET /asesmen/saya

**Tabel:** `siswa`, `asesmen_karakteristik`, `asesmen_riasec`, `asesmen_multiple_intelligence`

**Rujukan implementasi:** AssessmentController::history/mine

## UC11 — Kelola dan verifikasi data pendukung

**Aktor:** Admin sekolah

**Prasyarat:** Admin aktif; pemilik setiap record diketahui.

**Alur utama:**

1. Isi rapor semester/mapel, TKA, presensi, organisasi, karakter dan prestasi sesuai sumber.
2. Validasi tipe/ukuran unggahan, pemindaian, periode, nilai dan relasi siswa.
3. Catat prestasi sekolah dengan status pending; admin meninjau isi dan bukti lalu memutuskan status.
4. Konfirmasi kelengkapan daftar setelah diperiksa; bobot prestasi ditentukan pada program, bukan pada record prestasi.

**Alternatif dan validasi:** Status pending berarti menunggu pemeriksaan, bukan penilaian bahwa sumber tidak sah. Prestasi pending tidak dihitung sebagai terverifikasi. Kolom belum lengkap atau kemungkinan pengulangan diminta konfirmasi sambil mempertahankan sumber.

**Hasil:** Data pendukung terstruktur dan status verifikasinya jelas.

**API/alur:** GET /siswa/{id}/data-pendukung; /siswa/{id}/pendukung/{type}; /nilai-rapor; /presensi; /kelengkapan

**Tabel:** `siswa`, `nilai_rapor`, `rekap_presensi`, `catatan_karakter`, `riwayat_organisasi`, `prestasi_murid`, `saudara_tiri`, `private_attachments`

**Rujukan implementasi:** StudentSupportController; ClamAvScanner; StudentProfile

## UC12 — Atur dan hitung program eligibilitas

**Aktor:** Admin sekolah

**Prasyarat:** Admin aktif; kebijakan program ditentukan sekolah.

**Alur utama:**

1. Atur kriteria, operator, ambang dan bobot program; perubahan menaikkan versi.
2. Atur bobot tingkat prestasi/metode serta syarat bantuan bila dibutuhkan.
3. Bangun profil dari data akademik/ekonomi/pendukung yang diketahui dan terverifikasi.
4. Hitung program untuk siswa terpilih; simpan evaluasi, versi dan fingerprint sumber.

**Alternatif dan validasi:** Syarat yang kurang menghasilkan Belum dinilai. Nilai tidak diketahui tidak dianggap nol. Hanya prestasi terverifikasi dan bantuan aktif memenuhi komponen terkait.

**Hasil:** Hasil evaluasi dapat ditelusuri ke versi kriteria dan data.

**API/alur:** /program-eligibilitas; POST /program-eligibilitas/{id}/hitung; GET /program-eligibilitas/{id}/hasil

**Tabel:** `program_eligibilitas`, `hasil_program_eligibilitas`, `siswa`, `nilai_rapor`, `orang_tua`, `bantuan_siswa`, `prestasi_murid`

**Rujukan implementasi:** SchoolEligibility; Criteria; EligibilityEvaluator

## UC13 — Lihat status eligibilitas

**Aktor:** Admin sekolah, Siswa

**Prasyarat:** Program tersedia dan lingkup pemilik teridentifikasi.

**Alur utama:**

1. Admin membuka hasil program atau siswa meminta ringkasan /saya.
2. Evaluasi status terkini terhadap sumber yang tersedia.
3. Admin melihat detail; siswa hanya status dan alasan komponen yang diizinkan.

**Alternatif dan validasi:** Data kurang tetap Belum dinilai. Data ekonomi dan ambang internal tidak dimasukkan ke proyeksi siswa.

**Hasil:** Status tampil tanpa membocorkan data pribadi lain.

**API/alur:** GET /program-eligibilitas/{id}/hasil; GET /eligibilitas/saya

**Tabel:** `program_eligibilitas`, `hasil_program_eligibilitas`, `siswa`

**Rujukan implementasi:** SchoolEligibility::studentResult; SchoolEligibilityController

## UC14 — Kelola penerimaan PT dan izin publikasi

**Aktor:** Admin sekolah

**Prasyarat:** Admin aktif; identitas alumni terhubung ke profil sekolah.

**Alur utama:**

1. Pilih siswa atau buat profil alumni lama tanpa akun/NISN perkiraan.
2. Isi kampus, prodi, jenjang, jalur/subjalur, pilihan serta status penerimaan.
3. Sertakan nilai sesuai jalur dan prestasi yang benar-benar milik siswa tersebut.
4. Publikasi adalah tindakan terpisah dengan bukti izin; perubahan isi mencabut publikasi lama.

**Alternatif dan validasi:** Sumber impor yang belum lengkap berada pada siswa.catatan sampai dikonfirmasi. Pelamar KIP-K tidak otomatis menjadi penerima. Tanggal mulai kuliah tidak disimpulkan dari penerimaan.

**Hasil:** Penerimaan dan relasi prestasi konsisten; publikasi mengikuti izin.

**API/alur:** /study-tracer; PATCH /study-tracer/{id}/publikasi

**Tabel:** `siswa`, `perguruan_tinggi`, `study_tracer`, `study_tracer_prestasi`, `prestasi_murid`, `buku_alumni`

**Rujukan implementasi:** StudyTracerController::save/publish; study_tracer_prestasi

## UC15 — Catat, revisi dan publikasikan progres kuliah

**Aktor:** Admin sekolah

**Prasyarat:** Penerimaan PT milik alumni tersedia; periode dan data progres nyata diketahui.

**Alur utama:**

1. Isi status pendidikan, periode, semester, IPK, KIP-K dan UKT; IPI hanya untuk Mandiri.
2. Catat beasiswa dengan status proses/diterima/ditolak per periode.
3. Periode baru menambah histori; koreksi membuat revisi baru dan menandai versi lama tidak current.
4. Izin publikasi progres dan izin nominal finansial dicatat terpisah.

**Alternatif dan validasi:** Periode duplikat ditolak 409. Hubungan penerimaan milik orang lain ditolak. Tidak membuat progres atau beasiswa dummy dari tabel penerimaan PT.

**Hasil:** Histori progres dan revisinya utuh.

**API/alur:** POST /alumni/{id}/progress; PATCH /alumni-progress/{id}; PATCH /alumni-progress/{id}/publikasi

**Tabel:** `alumni_progress`, `alumni_progress_beasiswa`, `study_tracer`, `siswa`, `basis_data_beasiswa`

**Rujukan implementasi:** AlumniService::saveProgress; StudyTracerController::progress/publishProgress

## UC16 — Lihat penerimaan dan progres alumni

**Aktor:** Admin sekolah, Siswa

**Prasyarat:** Token aktif; admin atau siswa memakai endpoint sesuai perannya.

**Alur utama:**

1. Cari/filter penerimaan berdasarkan alumni, tahun lulus, kampus, prodi dan jalur.
2. Admin menerima detail penuh sesuai modul admin.
3. Siswa hanya menerima penerimaan dan progres yang sudah berizin.
4. Sertakan prestasi publik milik alumni; hilangkan kontak, identitas sensitif dan bukti privat.

**Alternatif dan validasi:** Nominal hanya tampil bila periode tersebut mengizinkan finansial. Draf, revisi lama dan prestasi milik siswa lain tidak ikut proyeksi siswa.

**Hasil:** Jejak alumni tampil dengan proyeksi privasi yang benar.

**API/alur:** GET /study-tracer; /study-tracer/{id}; GET /jejak-alumni; /jejak-alumni/{id}

**Tabel:** `study_tracer`, `study_tracer_prestasi`, `prestasi_murid`, `alumni_progress`, `alumni_progress_beasiswa`, `perguruan_tinggi`, `siswa`

**Rujukan implementasi:** StudyTracerController::query/studentIndex/studentShow; AlumniService::admission/progress

## UC17 — Lihat timeline pendidikan alumni

**Aktor:** Admin sekolah, Siswa

**Prasyarat:** Admin memilih alumni; siswa hanya mengakses jejak terpublikasi.

**Alur utama:**

1. Ambil milestone sekolah, penerimaan PT dan progres yang benar-benar tercatat.
2. Urutkan menurut tanggal nyata; bila hanya tahun diketahui, tampilkan tahun.
3. Pakai proyeksi privasi dan sertakan prestasi terkait yang boleh ditampilkan.

**Alternatif dan validasi:** Tidak membuat tanggal 1 Januari atau tanggal mulai kuliah dari tahun lulus. Catatan sumber tanpa tanggal tidak menjadi milestone palsu.

**Hasil:** Timeline konsisten dengan rekam data dan izin.

**API/alur:** GET /alumni/{id}/timeline; detail /jejak-alumni/{id}

**Tabel:** `siswa`, `riwayat_kelas`, `study_tracer`, `alumni_progress`, `alumni_progress_beasiswa`

**Rujukan implementasi:** AlumniService::timeline

## UC18 — Analitik beasiswa dan publikasi pola agregat

**Aktor:** Admin sekolah

**Prasyarat:** Admin aktif; progres dan data yang diperlukan tersedia.

**Alur utama:**

1. Filter angkatan, prodi, jenis PT, beasiswa dan periode.
2. Ringkasan memakai progres terbaru; tren memakai periode serta alumni unik tanpa revisi lama ganda.
3. Tampilkan status beasiswa proses/diterima/ditolak dan ekspor CSV yang aman.
4. Buat pola dari agregat berizin minimal lima alumni; admin menentukan publikasi snapshot.

**Alternatif dan validasi:** Data kosong tetap kosong, bukan kesimpulan bahwa semua alumni tidak menerima beasiswa. Kelompok kecil disembunyikan. Pencabutan izin anggota menyembunyikan snapshot terkait.

**Hasil:** Analitik dapat ditelusuri tanpa mengekspos identitas murid pada pola publik.

**API/alur:** /analitik-beasiswa; /analitik-beasiswa/export; /analitik-beasiswa/pola; PATCH /siswa/{id}/izin-analitik

**Tabel:** `alumni_progress`, `alumni_progress_beasiswa`, `study_tracer`, `siswa`, `basis_data_beasiswa`, `published_cohort_summaries`

**Rujukan implementasi:** Analytics; AnalyticsController; published_cohort_summaries

## UC19 — Kelola dan cari katalog beasiswa

**Aktor:** Admin sekolah

**Prasyarat:** Admin aktif; informasi program berasal dari sumber resmi.

**Alur utama:**

1. Isi nama, penyelenggara, jenjang, durasi, periode, jadwal, tautan dan kriteria.
2. Hubungkan program eligibilitas bila diperlukan.
3. Cari/paginasi katalog; filter SMA atau Kuliah juga menyertakan Keduanya.
4. Arsipkan program yang tidak aktif agar histori tetap dapat dibaca melalui snapshot.

**Alternatif dan validasi:** Query tidak sah ditolak 422. Katalog kosong tidak diisi program buatan AI. Program yang diarsipkan tidak memasok evaluasi baru.

**Hasil:** Katalog dan persyaratan menjadi acuan rekomendasi.

**API/alur:** GET/POST /beasiswa; PUT/DELETE /beasiswa/{id}

**Tabel:** `basis_data_beasiswa`, `kriteria_kelayakan_beasiswa`, `program_eligibilitas`

**Rujukan implementasi:** BasisDataBeasiswaController; KriteriaKelayakanBeasiswa

## UC20 — Buat dan tinjau rekomendasi beasiswa AI

**Aktor:** Admin sekolah

**Prasyarat:** Admin aktif; target jenjang diketahui dan katalog sesuai tersedia.

**Alur utama:**

1. StudentProfile membangun profil; backend memilih kandidat aktif sesuai jenjang.
2. EligibilityEvaluator menghitung skor serta checklist dari kriteria, bukan dari tebakan AI.
3. Gemini menjelaskan alasan dan langkah persiapan dari kandidat tanpa nama/NISN/kontak/angka penghasilan.
4. Validasi respons; simpan rekomendasi dan detail sebagai pending dalam transaksi.
5. Admin meninjau; persetujuan memerlukan fingerprint sama, kriteria lengkap dan kandidat yang memenuhi.

**Alternatif dan validasi:** Tanpa jenjang/kandidat: 422. Provider gagal/respons tidak sah: tidak menyimpan draft parsial. Sumber berubah: 409 dan buat ulang. Data kurang tidak dapat disetujui. Gemini tidak mengubah skor dan tidak dilatih dari spreadsheet ini.

**Hasil:** Draft pending atau hasil disetujui/ditolak dengan pencatat dan snapshot.

**API/alur:** POST /siswa/{id}/rekomendasi-beasiswa/generate; PATCH /rekomendasi-beasiswa/{id}/review

**Tabel:** `siswa`, `program_eligibilitas`, `basis_data_beasiswa`, `kriteria_kelayakan_beasiswa`, `rekomendasi_beasiswa`, `rekomendasi_beasiswa_detail`

**Rujukan implementasi:** ScholarshipRecommendationService; EligibilityEvaluator; GeminiScholarshipExplainer; RekomendasiBeasiswaController

## UC21 — Lihat rekomendasi beasiswa sendiri

**Aktor:** Siswa

**Prasyarat:** Siswa terhubung ke profil dan token sah.

**Alur utama:**

1. Identifikasi siswa dari pemilik token; jangan menerima ID siswa bebas.
2. Ambil hanya rekomendasi miliknya yang disetujui.
3. Tampilkan snapshot informasi, skor kecocokan, checklist, alasan dan persiapan.
4. Pola alumni ditambahkan hanya bila agregat tersebut sudah berizin dan terpublikasi.

**Alternatif dan validasi:** Pending/ditolak tidak dibuka. Tidak ada hasil disetujui: daftar kosong. Skor kecocokan bukan probabilitas lolos.

**Hasil:** Rekomendasi personal terbaca tanpa catatan internal admin.

**API/alur:** GET /rekomendasi-beasiswa/saya

**Tabel:** `users`, `siswa`, `rekomendasi_beasiswa`, `rekomendasi_beasiswa_detail`, `published_cohort_summaries`

**Rujukan implementasi:** RekomendasiBeasiswaController::mine

## UC22 — Kelola dan tinjau rekomendasi jurusan

**Aktor:** Admin sekolah

**Prasyarat:** Admin aktif; klaster dan bobot ditetapkan sekolah.

**Alur utama:**

1. Atur bobot nilai/RIASEC/MI total 100 dan bobot mapel yang dibutuhkan.
2. Ambil data formal terpilih; hitung hanya bila seluruh komponen berbobot tersedia.
3. Simpan batch pending dengan detail, snapshot kriteria dan fingerprint.
4. Admin menyetujui atau menolak; perubahan sumber meminta generate ulang.

**Alternatif dan validasi:** Catatan peminatan mentah tanpa tanggal/skor belum otomatis menjadi input komponen formal. Data tidak cukup tidak diberi skor palsu. Tidak mengklaim prediksi diterima PT.

**Hasil:** Hasil jurusan yang ditinjau dapat dibaca siswa.

**API/alur:** /klaster-jurusan; POST /siswa/{id}/rekomendasi-jurusan/generate; PATCH /rekomendasi-jurusan/{batch}/review

**Tabel:** `klaster_prodi`, `bobot_mapel_klaster`, `rekomendasi_prodi`, `siswa`, `nilai_rapor`, `asesmen_karakteristik`, `asesmen_multiple_intelligence`

**Rujukan implementasi:** MajorRecommendation::sources/generate; MajorRecommendationController

## UC23 — Lihat rekomendasi jurusan sendiri

**Aktor:** Siswa

**Prasyarat:** Siswa terhubung ke master sekolah.

**Alur utama:**

1. Tentukan siswa melalui token.
2. Ambil batch terbaru yang disetujui dalam lingkup profil sendiri.
3. Tampilkan jurusan, alasan dan komponen yang diperbolehkan.

**Alternatif dan validasi:** Draft/ditolak dan batch siswa lain tidak tersedia. Belum ada hasil disetujui menghasilkan daftar kosong.

**Hasil:** Hasil jurusan personal bersifat read-only.

**API/alur:** GET /rekomendasi-jurusan/saya

**Tabel:** `users`, `siswa`, `rekomendasi_prodi`, `klaster_prodi`

**Rujukan implementasi:** MajorRecommendationController::mine

## UC24 — Logout dan cabut token perangkat

**Aktor:** Admin sekolah, Siswa

**Prasyarat:** Pengguna sedang memakai token.

**Alur utama:**

1. Pengguna memilih logout.
2. Backend mencabut current access token.
3. Frontend membersihkan token lokal dan kembali ke login.

**Alternatif dan validasi:** Token telah tidak berlaku tetap diarahkan ke login. Logout hanya mencabut token perangkat yang dipakai; perangkat lain tetap aktif. Ganti/reset password mencabut seluruh token akun.

**Hasil:** Token perangkat yang dicabut tidak bisa dipakai ulang.

**API/alur:** POST /logout

**Tabel:** `personal_access_tokens`

**Rujukan implementasi:** AuthController::logout; SessionIssuer::revoke

## UC25 — Lihat pola alumni terpublikasi

**Aktor:** Siswa

**Prasyarat:** Siswa aktif; snapshot pola disetujui admin dan izin anggota masih sah.

**Alur utama:**

1. Siswa meminta ringkasan pola.
2. Backend menyaring snapshot terpublikasi dan memeriksa izin agregasi.
3. Kirim DTO agregat tanpa daftar identitas maupun penghasilan individual.

**Alternatif dan validasi:** Anggota mencabut izin atau kelompok tidak cukup: pola tidak tampil. Dataset penerimaan PT saja belum membentuk pola penerima beasiswa.

**Hasil:** Pola agregat membantu pemahaman tanpa membuka identitas.

**API/alur:** GET /pola-alumni

**Tabel:** `published_cohort_summaries`, `siswa`, `basis_data_beasiswa`

**Rujukan implementasi:** Analytics::published; AnalyticsController::published

## UC26 — Impor dan rekonsiliasi data sekolah

**Aktor:** Admin sekolah

**Prasyarat:** Backend memperoleh sumber sekolah dan hak akses sah; cadangan dibuat sebelum penulisan.

**Alur utama:**

1. Baca semua tab, pisahkan tabel individu, rekap dan lampiran pendukung.
2. Cocokkan identitas, hubungan dan pengulangan; petakan hanya nilai yang didukung sumber.
3. Simpan data lengkap ke tabel domain; simpan sumber yang belum lengkap pada siswa.catatan serta daftar konfirmasi.
4. Prestasi baru berstatus pending; asesmen tanpa tanggal tetap sebagai catatan, tanpa skor/tanggal perkiraan.
5. Simpan dalam transaksi setelah backup; periksa data lama, jumlah baris, privasi dan impor ulang tanpa duplikat.

**Alternatif dan validasi:** NIS sama untuk nama berbeda tidak dipakai untuk menggabungkan profil. Baris yang mungkin mengulang prestasi diminta konfirmasi; sumber sekolah dipertahankan. Batch rekonsiliasi ini pekerjaan backend terkurasi, bukan klaim bahwa endpoint unggah umum mendukung semua format/tab.

**Hasil:** Data sekolah bertambah dengan jejak sumber; kelengkapan dan status verifikasi tetap terlihat.

**API/alur:** Batch backend terkurasi; POST /alumni/import memiliki cakupan template tersendiri

**Tabel:** `siswa`, `perguruan_tinggi`, `buku_alumni`, `study_tracer`, `prestasi_murid`

**Rujukan implementasi:** Batch impor sekolah dan peminatan 2 Nov 2026; siswa.catatan; laporan impor terverifikasi

## UC27 — Kelola referensi sekolah dan kampus

**Aktor:** Admin sekolah

**Prasyarat:** Administrator aktif.

**Alur utama:**

1. Kelola referensi kelas, mapel, bantuan, hobi, cita-cita dan kampus.
2. Validasi nilai dan identitas referensi sebelum simpan.
3. Gunakan ID referensi pada data murid/program; penghapusan referensi yang masih dipakai ditolak.

**Alternatif dan validasi:** Nama singkat yang belum pasti tidak dipaksa menjadi kampus tertentu. Kelas memerlukan tahun ajaran yang benar sebelum dipakai sebagai histori.

**Hasil:** Referensi mendukung konsistensi seluruh modul.

**API/alur:** GET/POST /referensi/{type}; PUT/DELETE /referensi/{type}/{id}

**Tabel:** `kelas`, `mata_pelajaran`, `jenis_bantuan`, `hobi`, `cita_cita`, `perguruan_tinggi`

**Rujukan implementasi:** ReferenceController

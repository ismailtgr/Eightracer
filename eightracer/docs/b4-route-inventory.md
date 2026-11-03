# Inventaris route kandidat teruji

114 route API, direfleksikan dari aplikasi kandidat. HEAD digabung dengan GET. Hak akhir tetap mengikuti middleware dan pemeriksaan controller; alias administrator kini menerima grant BK sah, tetapi pemberian grant sendiri tetap administrator.

| Method | URL | Controller | Middleware |
|---|---|---|---|
| POST | /api/register | SchoolAuthController@register | api, throttle:otp-request |
| POST | /api/login | SchoolAuthController@login | api, throttle:login |
| POST | /api/forgot-password | SchoolAuthController@forgot | api, throttle:otp-request |
| POST | /api/reset-password | SchoolAuthController@reset | api, throttle:otp-verify |
| GET,HEAD | /api/beasiswa | BasisDataBeasiswaController@index | api, auth:sanctum, token.lifetime, throttle:api |
| POST | /api/beasiswa | BasisDataBeasiswaController@store | api, auth:sanctum, token.lifetime, throttle:api |
| PUT | /api/beasiswa/{beasiswa} | BasisDataBeasiswaController@update | api, auth:sanctum, token.lifetime, throttle:api |
| DELETE | /api/beasiswa/{beasiswa} | BasisDataBeasiswaController@archive | api, auth:sanctum, token.lifetime, throttle:api |
| GET,HEAD | /api/rekomendasi-beasiswa/saya | RekomendasiBeasiswaController@mine | api, auth:sanctum, token.lifetime, throttle:api |
| GET,HEAD | /api/rekomendasi-beasiswa | RekomendasiBeasiswaController@index | api, auth:sanctum, token.lifetime, throttle:api |
| POST | /api/siswa/{siswa}/rekomendasi-beasiswa/generate | RekomendasiBeasiswaController@generate | api, auth:sanctum, token.lifetime, throttle:api, throttle:5,1 |
| GET,HEAD | /api/siswa/{siswa}/rekomendasi-beasiswa/preview | RekomendasiBeasiswaController@preview | api, auth:sanctum, token.lifetime, throttle:api, administrator, throttle:10,1 |
| PATCH | /api/rekomendasi-beasiswa/{rekomendasi}/review | RekomendasiBeasiswaController@review | api, auth:sanctum, token.lifetime, throttle:api |
| POST | /api/logout | AuthController@logout | api, auth:sanctum, token.lifetime, throttle:api |
| GET,HEAD | /api/me | Closure | api, auth:sanctum, token.lifetime, throttle:api |
| POST | /api/alumni/import | AlumniImportController@import | api, auth:sanctum, token.lifetime, throttle:api, administrator, throttle:2,1 |
| GET,HEAD | /api/siswa | SiswaController@index | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| POST | /api/siswa | SiswaController@store | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| GET,HEAD | /api/siswa/{siswa} | SiswaController@show | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| PUT,PATCH | /api/siswa/{siswa} | SiswaController@update | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| DELETE | /api/siswa/{siswa} | SiswaController@destroy | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| GET,HEAD | /api/siswa/{siswa}/orang-tua | KeluargaSiswaController@getOrangTua | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| POST | /api/siswa/{siswa}/orang-tua | KeluargaSiswaController@saveOrangTua | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| GET,HEAD | /api/siswa/{siswa}/wali | KeluargaSiswaController@getWali | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| POST | /api/siswa/{siswa}/wali | KeluargaSiswaController@saveWali | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| GET,HEAD | /api/siswa/{siswa}/media-sosial | KeluargaSiswaController@getMediaSosial | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| POST | /api/siswa/{siswa}/media-sosial | KeluargaSiswaController@addMediaSosial | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| GET,HEAD | /api/siswa/{siswa}/bantuan | KeluargaSiswaController@getBantuan | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| POST | /api/siswa/{siswa}/bantuan | KeluargaSiswaController@addBantuan | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| GET,HEAD | /api/alumni/progress | AlumniProgressController@index | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| GET,HEAD | /api/alumni/{siswa}/progress | AlumniProgressController@showBySiswa | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| POST | /api/alumni/{siswa}/progress | AlumniProgressController@store | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| GET,HEAD | /api/alumni/{siswa}/timeline | AlumniProgressController@timeline | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| GET,HEAD | /api/faq | DashboardController@faq | api |
| POST | /api/register/verify | SchoolAuthController@verifyRegistration | api, throttle:otp-verify |
| POST | /api/forgot-password/verify | SchoolAuthController@verifyReset | api, throttle:otp-verify |
| POST | /api/auth/google/challenge | GoogleAuthController@challenge | api, throttle:10,1 |
| POST | /api/auth/google | GoogleAuthController@login | api, throttle:login |
| GET,HEAD | /api/beranda | DashboardController@index | api, auth:sanctum, token.lifetime, throttle:api |
| GET,HEAD | /api/profile | ProfileController@show | api, auth:sanctum, token.lifetime, throttle:api |
| PATCH | /api/profile | ProfileController@update | api, auth:sanctum, token.lifetime, throttle:api |
| POST | /api/profile/password/otp | ProfileController@passwordOtp | api, auth:sanctum, token.lifetime, throttle:api, throttle:otp-request |
| PUT | /api/profile/password | ProfileController@password | api, auth:sanctum, token.lifetime, throttle:api, throttle:otp-verify |
| POST | /api/profile/email/otp | ProfileController@emailOtp | api, auth:sanctum, token.lifetime, throttle:api, throttle:otp-request |
| POST | /api/profile/email/verify | ProfileController@emailVerify | api, auth:sanctum, token.lifetime, throttle:api, throttle:otp-verify |
| POST | /api/profile/photo | ProfileController@photo | api, auth:sanctum, token.lifetime, throttle:api, throttle:5,1 |
| GET,HEAD | /api/profile/photo | ProfileController@photoRead | api, auth:sanctum, token.lifetime, throttle:api |
| POST | /api/profile/google | GoogleAuthController@link | api, auth:sanctum, token.lifetime, throttle:api, throttle:otp-verify |
| GET,HEAD | /api/lampiran/{id} | AttachmentController@download | api, auth:sanctum, token.lifetime, throttle:api |
| GET,HEAD | /api/asesmen/saya | AssessmentController@mine | api, auth:sanctum, token.lifetime, throttle:api, App\Http\Middleware\RequireStudent |
| GET,HEAD | /api/eligibilitas/saya | SchoolEligibilityController@mine | api, auth:sanctum, token.lifetime, throttle:api, App\Http\Middleware\RequireStudent |
| GET,HEAD | /api/rekomendasi-jurusan/saya | MajorRecommendationController@mine | api, auth:sanctum, token.lifetime, throttle:api, App\Http\Middleware\RequireStudent |
| GET,HEAD | /api/jejak-alumni | StudyTracerController@studentIndex | api, auth:sanctum, token.lifetime, throttle:api, App\Http\Middleware\RequireStudent |
| GET,HEAD | /api/jejak-alumni/{id} | StudyTracerController@studentShow | api, auth:sanctum, token.lifetime, throttle:api, App\Http\Middleware\RequireStudent |
| GET,HEAD | /api/pola-alumni | AnalyticsController@published | api, auth:sanctum, token.lifetime, throttle:api, App\Http\Middleware\RequireStudent |
| GET,HEAD | /api/referensi/{type} | ReferenceController@index | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| POST | /api/referensi/{type} | ReferenceController@store | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| PUT | /api/referensi/{type}/{id} | ReferenceController@update | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| DELETE | /api/referensi/{type}/{id} | ReferenceController@destroy | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| PUT | /api/siswa/{siswa}/ekonomi | StudentEconomyController@save | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| PUT,DELETE | /api/siswa/{siswa}/keluarga/{type}/{id} | StudentEconomyController@updateRelated | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| GET,HEAD | /api/siswa/{siswa}/data-pendukung | StudentSupportController@show | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| PUT | /api/siswa/{siswa}/nilai-rapor | StudentSupportController@grades | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| PUT | /api/siswa/{siswa}/presensi | StudentSupportController@attendance | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| PATCH | /api/siswa/{siswa}/kelengkapan | StudentSupportController@completeness | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| POST | /api/siswa/{siswa}/kelas | StudentSupportController@classroom | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| PUT | /api/siswa/{siswa}/minat | StudentSupportController@interests | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| POST | /api/siswa/{siswa}/pendukung/{type} | StudentSupportController@record | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| PUT | /api/siswa/{siswa}/pendukung/{type}/{id} | StudentSupportController@record | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| DELETE | /api/siswa/{siswa}/pendukung/{type}/{id} | StudentSupportController@destroy | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| GET,HEAD | /api/siswa/{siswa}/asesmen | AssessmentController@history | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| POST | /api/siswa/{siswa}/asesmen | AssessmentController@store | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| POST | /api/siswa/{siswa}/lampiran | AttachmentController@store | api, auth:sanctum, token.lifetime, throttle:api, administrator, throttle:5,1 |
| GET,HEAD | /api/program-eligibilitas | SchoolEligibilityController@index | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| POST | /api/program-eligibilitas | SchoolEligibilityController@save | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| PUT | /api/program-eligibilitas/{id} | SchoolEligibilityController@save | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| DELETE | /api/program-eligibilitas/{id} | SchoolEligibilityController@archive | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| POST | /api/program-eligibilitas/{id}/hitung | SchoolEligibilityController@calculate | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| GET,HEAD | /api/program-eligibilitas/{id}/hasil | SchoolEligibilityController@results | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| GET,HEAD | /api/klaster-jurusan | MajorRecommendationController@clusters | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| POST | /api/klaster-jurusan | MajorRecommendationController@saveCluster | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| PUT | /api/klaster-jurusan/{id} | MajorRecommendationController@saveCluster | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| DELETE | /api/klaster-jurusan/{id} | MajorRecommendationController@archive | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| GET,HEAD | /api/rekomendasi-jurusan | MajorRecommendationController@index | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| POST | /api/siswa/{siswa}/rekomendasi-jurusan/generate | MajorRecommendationController@generate | api, auth:sanctum, token.lifetime, throttle:api, administrator, throttle:5,1 |
| PATCH | /api/rekomendasi-jurusan/{batch}/review | MajorRecommendationController@review | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| GET,HEAD | /api/study-tracer | StudyTracerController@index | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| POST | /api/study-tracer | StudyTracerController@save | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| GET,HEAD | /api/study-tracer/{id} | StudyTracerController@show | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| PUT | /api/study-tracer/{id} | StudyTracerController@save | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| DELETE | /api/study-tracer/{id} | StudyTracerController@destroy | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| PATCH | /api/study-tracer/{id}/publikasi | StudyTracerController@publish | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| PATCH | /api/alumni-progress/{id} | StudyTracerController@progress | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| PATCH | /api/alumni-progress/{id}/publikasi | StudyTracerController@publishProgress | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| GET,HEAD | /api/analitik-beasiswa | AnalyticsController@index | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| GET,HEAD | /api/analitik-beasiswa/export | AnalyticsController@export | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| PATCH | /api/siswa/{siswa}/izin-analitik | AnalyticsController@consent | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| GET,HEAD | /api/analitik-beasiswa/pola | AnalyticsController@cohorts | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| POST | /api/analitik-beasiswa/pola | AnalyticsController@create | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| PATCH | /api/analitik-beasiswa/pola/{id}/publikasi | AnalyticsController@publish | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| GET,HEAD | /api/profil-siswa/saya | StudentInitialProfileController@show | api, auth:sanctum, token.lifetime, throttle:api, App\Http\Middleware\RequireStudent |
| POST | /api/profil-siswa/saya/submission | StudentInitialProfileController@submit | api, auth:sanctum, token.lifetime, throttle:api, App\Http\Middleware\RequireStudent, throttle:scholarship-chat |
| POST | /api/chat-beasiswa | ScholarshipChatController@mine | api, auth:sanctum, token.lifetime, throttle:api, App\Http\Middleware\RequireStudent, throttle:scholarship-chat |
| PUT | /api/school-staff/{user}/bk-access | SchoolStaffController@save | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| GET,HEAD | /api/imports/templates/{module} | BulkImportController@template | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| POST | /api/imports | BulkImportController@preview | api, auth:sanctum, token.lifetime, throttle:api, administrator, throttle:bulk-preview |
| GET,HEAD | /api/imports/{batch} | BulkImportController@show | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| POST | /api/imports/{batch}/commit | BulkImportController@commit | api, auth:sanctum, token.lifetime, throttle:api, administrator, throttle:bulk-commit |
| GET,HEAD | /api/dashboard/ukt-ipk | FinancialDashboardController@index | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| GET,HEAD | /api/buku-alumni/pdf | FinancialDashboardController@book | api, auth:sanctum, token.lifetime, throttle:api, administrator, throttle:book-pdf |
| POST | /api/siswa/{siswa}/chat-beasiswa | ScholarshipChatController@staff | api, auth:sanctum, token.lifetime, throttle:api, administrator, throttle:scholarship-chat |
| GET,HEAD | /api/profile-submissions | StudentInitialProfileController@index | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| GET,HEAD | /api/profile-submissions/{submission} | StudentInitialProfileController@detail | api, auth:sanctum, token.lifetime, throttle:api, administrator |
| PATCH | /api/profile-submissions/{submission}/review | StudentInitialProfileController@review | api, auth:sanctum, token.lifetime, throttle:api, administrator |

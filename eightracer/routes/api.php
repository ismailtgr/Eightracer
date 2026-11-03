<?php

use App\Http\Controllers\Api\Admin\FeedbackStrategiController;
use App\Http\Controllers\Api\Admin\GuruBkController;
use App\Http\Controllers\Api\Admin\ManajemenDataController;
use App\Http\Controllers\Api\Admin\RekomendasiMapelController;
use App\Http\Controllers\Api\Admin\ValidasiRekomendasiController;
use App\Http\Controllers\Api\AlumniImportController;
use App\Http\Controllers\Api\AsesmenImportController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NilaiRaporImportController;
use App\Http\Controllers\Api\SiswaImportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login'])->name('login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', function (Request $request) {
        return $request->user();
    });

    // [Menu 1 - UC-06] Pusat Manajemen Data: Unggah Berkas, Tabel, Modal Detail Informasi, & Pemindahan Kelas
    Route::post('/alumni/import',                                       [AlumniImportController::class, 'import']);
    Route::post('/siswa/import',                                        [SiswaImportController::class, 'import']);
    Route::post('/nilai-rapor/import',                                  [NilaiRaporImportController::class, 'import']);
    Route::post('/asesmen/import',                                      [AsesmenImportController::class, 'import']);
    Route::get('/admin/manajemen-data/siswa',                           [ManajemenDataController::class, 'index']);
    Route::get('/admin/manajemen-data/siswa/{siswa}',                   [ManajemenDataController::class, 'show']);
    Route::put('/admin/manajemen-data/siswa/{siswa}/pindah-kelas',      [ManajemenDataController::class, 'pindahKelas']);

    // [Menu 2 - UC-07] Dashboard Konseling & Meninjau Draf Hasil Rekomendasi Mapel
    Route::get('/admin/dashboard-konseling',                            [RekomendasiMapelController::class, 'dashboard']);
    Route::get('/admin/rekomendasi',                                    [RekomendasiMapelController::class, 'index']);
    Route::post('/admin/rekomendasi/generate-all',                      [RekomendasiMapelController::class, 'generateAll']);
    Route::get('/admin/rekomendasi/item/{rekomendasi}',                 [RekomendasiMapelController::class, 'showItem']);
    Route::put('/admin/rekomendasi/item/{rekomendasi}',                 [RekomendasiMapelController::class, 'updateDraf']);
    Route::get('/admin/rekomendasi/{siswa}',                            [RekomendasiMapelController::class, 'show']);
    Route::post('/admin/rekomendasi/{siswa}/generate',                  [RekomendasiMapelController::class, 'generate']);

    // [Menu 3 - UC-08 & UC-09] Validasi, Kunci Permanen, dan Penerbitan Surat Rekomendasi (SK)
    Route::get('/admin/validasi-rekomendasi',                           [ValidasiRekomendasiController::class, 'index']);
    Route::post('/admin/validasi-rekomendasi/validasi-massal',          [ValidasiRekomendasiController::class, 'validasiMassal']);
    Route::get('/admin/validasi-rekomendasi/{siswa}',                   [ValidasiRekomendasiController::class, 'show']);
    Route::put('/admin/validasi-rekomendasi/{siswa}/draft',             [ValidasiRekomendasiController::class, 'simpanDraft']);
    Route::post('/admin/validasi-rekomendasi/{siswa}/validasi-kunci',   [ValidasiRekomendasiController::class, 'validasiDanKunci']);
    Route::post('/admin/validasi-rekomendasi/{siswa}/buka-kunci',       [ValidasiRekomendasiController::class, 'bukaKunci']);

    // [Menu 4 - UC-10] Mengelola Daftar Nama Guru BK (Direktori Guru BK)
    Route::get('/admin/guru-bk',                                        [GuruBkController::class, 'index']);
    Route::post('/admin/guru-bk',                                       [GuruBkController::class, 'store']);
    Route::get('/admin/guru-bk/{guru}',                                 [GuruBkController::class, 'show']);
    Route::put('/admin/guru-bk/{guru}',                                 [GuruBkController::class, 'update']);
    Route::post('/admin/guru-bk/{guru}',                                [GuruBkController::class, 'update']);
    Route::delete('/admin/guru-bk/{guru}/foto',                         [GuruBkController::class, 'hapusFoto']);
    Route::delete('/admin/guru-bk/{guru}',                              [GuruBkController::class, 'destroy']);

    // [Menu 4 - UC-11] Memberikan Umpan Balik Strategi Belajar Siswa (Feedback Strategi Belajar)
    Route::get('/admin/feedback-strategi',                              [FeedbackStrategiController::class, 'index']);
    Route::get('/admin/feedback-strategi/{siswa}',                      [FeedbackStrategiController::class, 'show']);
    Route::post('/admin/feedback-strategi/{siswa}/simpan',              [FeedbackStrategiController::class, 'simpanFeedback']);
    Route::post('/admin/feedback-strategi/{siswa}/lewati',              [FeedbackStrategiController::class, 'lewati']);
    Route::delete('/admin/feedback-strategi/item/{catatanBimbingan}',   [FeedbackStrategiController::class, 'destroy']);
});

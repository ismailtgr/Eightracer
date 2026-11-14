<?php

use App\Http\Controllers\Api\AlumniImportController;
use App\Http\Controllers\Api\AlumniProgressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BasisDataBeasiswaController;
use App\Http\Controllers\Api\KeluargaSiswaController;
use App\Http\Controllers\Api\RekomendasiBeasiswaController;
use App\Http\Controllers\Api\SchoolAuthController;
use App\Http\Controllers\Api\SchoolAccountController;
use App\Http\Controllers\Api\SiswaController;
use App\Services\Auth\SessionIssuer;
use App\Http\Controllers\Api\AlumniFormController;
use App\Http\Controllers\Api\PerguruanTinggiController;
use App\Http\Controllers\Api\AlumniController;
use App\Http\Controllers\Api\AlumniStatistikController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/internal/run-scheduler', \App\Http\Controllers\Api\InternalSchedulerController::class)
    ->middleware('throttle:6,1');

Route::get('/perguruan-tinggi', [PerguruanTinggiController::class, 'index']);

Route::post('/register', [SchoolAuthController::class, 'register'])->middleware('throttle:otp-request');
Route::post('/login', [SchoolAuthController::class, 'login'])->middleware('throttle:login');

Route::post('/forgot-password', [SchoolAuthController::class, 'forgot'])->middleware('throttle:otp-request');
Route::post('/reset-password', [SchoolAuthController::class, 'reset'])->middleware('throttle:otp-verify');

Route::middleware(['auth:sanctum', 'token.lifetime', 'throttle:api'])->group(function () {
    Route::prefix('alumni-form')->group(function () {
        Route::get('/status', [AlumniFormController::class, 'status']);
        Route::post('/step-1', [AlumniFormController::class, 'step1']);
        Route::post('/step-2', [AlumniFormController::class, 'step2']);
        Route::post('/step-3', [AlumniFormController::class, 'step3']);
        Route::post('/step-4', [AlumniFormController::class, 'step4']);
        Route::get('/summary', [AlumniFormController::class, 'summary']);
    });
    Route::get('/beasiswa', [BasisDataBeasiswaController::class, 'index']);
    Route::get('/beasiswa/{beasiswa}', [BasisDataBeasiswaController::class, 'show']);
    Route::post('/beasiswa', [BasisDataBeasiswaController::class, 'store']);
    Route::put('/beasiswa/{beasiswa}', [BasisDataBeasiswaController::class, 'update']);
    Route::delete('/beasiswa/{beasiswa}', [BasisDataBeasiswaController::class, 'archive']);
    Route::get('/rekomendasi-beasiswa/penerima', [RekomendasiBeasiswaController::class, 'recipients']);
    Route::get('/rekomendasi-beasiswa/saya', [RekomendasiBeasiswaController::class, 'mine']);
    Route::get('/rekomendasi-beasiswa', [RekomendasiBeasiswaController::class, 'index']);
    Route::get('/rekomendasi-beasiswa/{rekomendasi}', [RekomendasiBeasiswaController::class, 'show'])->whereNumber('rekomendasi');
    Route::post('/siswa/{siswa}/rekomendasi-beasiswa/generate', [RekomendasiBeasiswaController::class, 'generate'])->middleware('throttle:5,1');
    Route::post('/siswa/{siswa}/rekomendasi-beasiswa/save', [RekomendasiBeasiswaController::class, 'save'])->whereNumber('siswa')->middleware('throttle:10,1');
    Route::get('/siswa/{siswa}/rekomendasi-beasiswa/preview', [RekomendasiBeasiswaController::class, 'preview'])->middleware(['administrator', 'throttle:10,1']);
    Route::patch('/rekomendasi-beasiswa/{rekomendasi}/review', [RekomendasiBeasiswaController::class, 'review']);
    Route::patch('/rekomendasi-beasiswa/{rekomendasi}/detail/{detail}', [RekomendasiBeasiswaController::class, 'updateDetail'])->whereNumber(['rekomendasi', 'detail']);
    Route::delete('/rekomendasi-beasiswa/{rekomendasi}/detail/{detail}', [RekomendasiBeasiswaController::class, 'deleteDetail'])->whereNumber(['rekomendasi', 'detail']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', function (Request $request) {
        return app(SessionIssuer::class)->profile($request->user());
    });

    Route::middleware('administrator')->group(function () {
        Route::post('/akun-siswa/provision', [SchoolAccountController::class, 'provision'])->middleware('throttle:2,1');

        // B3 directory contract is preserved; B4 directory uses /b4/alumni.
        Route::get('/alumni/statistik', [AlumniStatistikController::class, 'index']);
        Route::get('/alumni', [AlumniController::class, 'index']);
        Route::get('/alumni/filter-options', [AlumniController::class, 'filterOptions']);
        Route::get('/alumni/{id}', [AlumniController::class, 'show'])->whereNumber('id');

        // --- Kelompok B3 ---
        Route::post('/alumni/import', [AlumniImportController::class, 'import'])->middleware('throttle:2,1');

        // --- Kelompok B4: Domain 1 (Biodata Murid & Keluarga) ---
        Route::apiResource('siswa', SiswaController::class);

        Route::prefix('siswa/{siswa}')->group(function () {
            // Data Orang Tua
            Route::get('/orang-tua', [KeluargaSiswaController::class, 'getOrangTua']);
            Route::post('/orang-tua', [KeluargaSiswaController::class, 'saveOrangTua']);

            // Data Wali
            Route::get('/wali', [KeluargaSiswaController::class, 'getWali']);
            Route::post('/wali', [KeluargaSiswaController::class, 'saveWali']);

            // Data Media Sosial
            Route::get('/media-sosial', [KeluargaSiswaController::class, 'getMediaSosial']);
            Route::post('/media-sosial', [KeluargaSiswaController::class, 'addMediaSosial']);

            // Data Bantuan
            Route::get('/bantuan', [KeluargaSiswaController::class, 'getBantuan']);
            Route::post('/bantuan', [KeluargaSiswaController::class, 'addBantuan']);
        });

        // --- Kelompok B4: Domain 5 (Rekam Jejak Alumni, UKT, Beasiswa & Timeline) ---
        Route::get('/alumni/progress', [AlumniProgressController::class, 'index']);
        Route::get('/alumni/{siswa}/progress', [AlumniProgressController::class, 'showBySiswa']);
        Route::post('/alumni/{siswa}/progress', [AlumniProgressController::class, 'store']);
        Route::get('/alumni/{siswa}/timeline', [AlumniProgressController::class, 'timeline']);
    });
});

require __DIR__.'/prd.php';

require __DIR__.'/b4.php';

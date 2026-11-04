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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [SchoolAuthController::class, 'register'])->middleware('throttle:otp-request');
Route::post('/login', [SchoolAuthController::class, 'login'])->middleware('throttle:login');

Route::post('/forgot-password', [SchoolAuthController::class, 'forgot'])->middleware('throttle:otp-request');
Route::post('/reset-password', [SchoolAuthController::class, 'reset'])->middleware('throttle:otp-verify');

Route::middleware(['auth:sanctum', 'token.lifetime', 'throttle:api'])->group(function () {
    Route::get('/beasiswa', [BasisDataBeasiswaController::class, 'index']);
    Route::get('/beasiswa/{beasiswa}', [BasisDataBeasiswaController::class, 'show']);
    Route::post('/beasiswa', [BasisDataBeasiswaController::class, 'store']);
    Route::put('/beasiswa/{beasiswa}', [BasisDataBeasiswaController::class, 'update']);
    Route::delete('/beasiswa/{beasiswa}', [BasisDataBeasiswaController::class, 'archive']);
    Route::get('/rekomendasi-beasiswa/saya', [RekomendasiBeasiswaController::class, 'mine']);
    Route::get('/rekomendasi-beasiswa', [RekomendasiBeasiswaController::class, 'index']);
    Route::post('/siswa/{siswa}/rekomendasi-beasiswa/generate', [RekomendasiBeasiswaController::class, 'generate'])->middleware('throttle:5,1');
    Route::get('/siswa/{siswa}/rekomendasi-beasiswa/preview', [RekomendasiBeasiswaController::class, 'preview'])->middleware(['administrator', 'throttle:10,1']);
    Route::patch('/rekomendasi-beasiswa/{rekomendasi}/review', [RekomendasiBeasiswaController::class, 'review']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', function (Request $request) {
        return app(SessionIssuer::class)->profile($request->user());
    });

    Route::middleware('administrator')->group(function () {
        Route::post('/akun-siswa/provision', [SchoolAccountController::class, 'provision'])->middleware('throttle:2,1');

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

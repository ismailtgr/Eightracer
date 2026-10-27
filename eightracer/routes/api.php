<?php

use App\Http\Controllers\Api\AlumniImportController;
use App\Http\Controllers\Api\AlumniProgressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\KeluargaSiswaController;
use App\Http\Controllers\Api\SiswaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', function (Request $request) {
        return $request->user();
    });

    // --- Kelompok B3 ---
    Route::post('/alumni/import', [AlumniImportController::class, 'import']);

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
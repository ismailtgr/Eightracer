<?php

use App\Http\Controllers\Api\AlumniImportController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AlumniFormController;
use App\Http\Controllers\Api\SiswaController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\SiswaAdminController;
use App\Http\Controllers\Api\AlumniAdminController;
use App\Http\Controllers\Api\ProdiAdminController;
use App\Http\Controllers\Api\AsesmenController;
use App\Http\Controllers\Api\AkademikController;
use App\Http\Controllers\Api\BobotAdminController;
use App\Http\Controllers\Api\EligibilitasEngineController;
use App\Http\Controllers\Api\BenchmarkAlumniController;
use App\Http\Controllers\Api\RankingController;
use App\Http\Controllers\Api\LaporanEligibilitasController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PerguruanTinggiController;
use App\Http\Controllers\Api\AlumniController;
use App\Http\Controllers\Api\AlumniStatistikController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

Route::get('/perguruan-tinggi', [PerguruanTinggiController::class, 'index']);
Route::get('/alumni/statistik', [AlumniStatistikController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('alumni-form')->group(function () {
        Route::get('/status', [AlumniFormController::class, 'status']);
        Route::post('/step-1', [AlumniFormController::class, 'step1']);
        Route::post('/step-2', [AlumniFormController::class, 'step2']);
        Route::post('/step-3', [AlumniFormController::class, 'step3']);
        Route::post('/step-4', [AlumniFormController::class, 'step4']);
        Route::get('/summary', [AlumniFormController::class, 'summary']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', function (Request $request) {
        return $request->user();
    });

    Route::post('/alumni/import', [AlumniImportController::class, 'import']);
    Route::get('/v1/siswa/profile', [SiswaController::class, 'getProfile']);
    Route::put('/v1/siswa/profile', [SiswaController::class, 'updateProfile']);
    Route::put('/v1/siswa/password', [SiswaController::class, 'updatePassword']);
    Route::apiResource('/v1/admin/users', UserController::class);
    Route::apiResource('/v1/admin/siswa', SiswaAdminController::class);
    Route::apiResource('/v1/admin/alumni', AlumniAdminController::class);
    Route::apiResource('/v1/admin/prodi', ProdiAdminController::class);
    Route::get('/v1/siswa/asesmen', [AsesmenController::class, 'getAsesmen']);
    Route::post('/v1/siswa/asesmen', [AsesmenController::class, 'storeOrUpdateAsesmen']);
    Route::get('/v1/siswa/akademik', [AkademikController::class, 'getAkademik']);
    Route::post('/v1/siswa/akademik/rapor', [AkademikController::class, 'storeOrUpdateNilaiRapor']);
    Route::post('/v1/siswa/akademik/prestasi', [AkademikController::class, 'storePrestasi']);
    Route::post('/v1/siswa/akademik/presensi-karakter', [AkademikController::class, 'storePresensiDanKarakter']);
    Route::get('/v1/admin/bobot', [BobotAdminController::class, 'getBobot']);
    Route::put('/v1/admin/bobot', [BobotAdminController::class, 'updateBobot']);
    Route::post('/v1/admin/engine/kalkulasi-eligibilitas', [EligibilitasEngineController::class, 'kalkulasiOtomatis']);
    Route::get('/v1/siswa/benchmark-alumni', [BenchmarkAlumniController::class, 'compareWithAlumni']);
    Route::get('/v1/ranking', [RankingController::class, 'getRankingList']);
    Route::get('/v1/siswa/ranking/detail', [RankingController::class, 'getDetailSkorSiswa']);
    Route::get('/v1/admin/ranking/{siswaId}/detail', [RankingController::class, 'getDetailSkorSiswa']);
    Route::get('/v1/admin/ranking/export/pdf', [LaporanEligibilitasController::class, 'exportPdf']);
    Route::get('/v1/admin/ranking/export/excel', [LaporanEligibilitasController::class, 'exportExcel']);

    Route::prefix('alumni')->group(function () {
        Route::get('/', [AlumniController::class, 'index']);
        Route::get('/filter-options', [AlumniController::class, 'filterOptions']);
        Route::get('/{id}', [AlumniController::class, 'show']);
    });
});
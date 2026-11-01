<?php

use App\Http\Controllers\Api\AlumniImportController;
use App\Http\Controllers\Api\AsesmenImportController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NilaiRaporImportController;
use App\Http\Controllers\Api\SiswaImportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', function (Request $request) {
        return $request->user();
    });

    Route::post('/alumni/import', [AlumniImportController::class, 'import']);
    Route::post('/siswa/import', [SiswaImportController::class, 'import']);
    Route::post('/nilai-rapor/import', [NilaiRaporImportController::class, 'import']);
    Route::post('/asesmen/import', [AsesmenImportController::class, 'import']);
});

<?php

use App\Http\Controllers\Api\AlumniImportController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AlumniFormController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

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
});
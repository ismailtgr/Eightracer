<?php

use App\Http\Controllers\Api\AlumniDirectoryController;
use App\Http\Controllers\Api\SchoolScholarshipAnalyticsController;
use App\Http\Controllers\Api\AlumniProgressEditorController;
use App\Http\Controllers\Api\BulkImportController;
use App\Http\Controllers\Api\FinancialDashboardController;
use App\Http\Controllers\Api\ScholarshipChatController;
use App\Http\Controllers\Api\ScholarshipSourceController;
use App\Http\Controllers\Api\SchoolStaffController;
use App\Http\Controllers\Api\StudentInitialProfileController;
use App\Http\Middleware\RequireStudent;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'token.lifetime', 'throttle:api'])->group(function () {
    Route::middleware(RequireStudent::class)->group(function () {
        Route::get('/profil-siswa/saya', [StudentInitialProfileController::class, 'show']);
        Route::post('/profil-siswa/saya/submission', [StudentInitialProfileController::class, 'submit'])->middleware('throttle:profile-submission');
        Route::post('/chat-beasiswa', [ScholarshipChatController::class, 'mine'])->middleware('throttle:scholarship-chat');
    });
    Route::middleware('administrator')->group(function () {
        Route::get('/alumni/{id}/progress-template', [AlumniProgressEditorController::class, 'template'])->whereNumber('id');
        Route::post('/alumni/{id}/college-progress', [AlumniProgressEditorController::class, 'save'])->whereNumber('id');
        Route::put('/alumni/{id}/college-progress/{progress}', [AlumniProgressEditorController::class, 'save'])->whereNumber(['id', 'progress']);
        Route::delete('/alumni/{id}/college-progress/{progress}', [AlumniProgressEditorController::class, 'archive'])->whereNumber(['id', 'progress']);
        Route::post('/alumni/{id}/progress-imports', [AlumniProgressEditorController::class, 'preview'])->whereNumber('id')->middleware('throttle:bulk-preview');
        Route::post('/alumni/{id}/progress-imports/{batch}/commit', [AlumniProgressEditorController::class, 'commit'])->whereNumber('id')->whereUuid('batch')->middleware('throttle:bulk-commit');
        Route::get('/analitik-beasiswa/sekolah/pdf', [SchoolScholarshipAnalyticsController::class, 'pdf'])->middleware('throttle:book-pdf');
        Route::get('/analitik-beasiswa/sekolah', [SchoolScholarshipAnalyticsController::class, 'index']);
        Route::get('/alumni', [AlumniDirectoryController::class, 'index']);
        Route::get('/alumni/{id}', [AlumniDirectoryController::class, 'show'])->whereNumber('id');
        Route::get('/riwayat-beasiswa-sumber', [ScholarshipSourceController::class, 'index']);
        Route::get('/riwayat-beasiswa-sumber/ringkasan', [ScholarshipSourceController::class, 'summary']);
        Route::put('/school-staff/{user}/bk-access', [SchoolStaffController::class, 'save'])->whereNumber('user');
        Route::get('/imports/templates/{module}', [BulkImportController::class, 'template']);
        Route::post('/imports', [BulkImportController::class, 'preview'])->middleware('throttle:bulk-preview');
        Route::get('/imports/{batch}', [BulkImportController::class, 'show'])->whereUuid('batch');
        Route::post('/imports/{batch}/commit', [BulkImportController::class, 'commit'])->whereUuid('batch')->middleware('throttle:bulk-commit');
        Route::get('/dashboard/ukt-ipk', [FinancialDashboardController::class, 'index']);
        Route::get('/buku-alumni/pdf', [FinancialDashboardController::class, 'book'])->middleware('throttle:book-pdf');
        Route::post('/siswa/{siswa}/chat-beasiswa', [ScholarshipChatController::class, 'staff'])->whereNumber('siswa')->middleware('throttle:scholarship-chat');
        Route::get('/profile-submissions', [StudentInitialProfileController::class, 'index']);
        Route::get('/profile-submissions/{submission}', [StudentInitialProfileController::class, 'detail'])->whereUuid('submission');
        Route::patch('/profile-submissions/{submission}/review', [StudentInitialProfileController::class, 'review'])->whereUuid('submission');
    });
});

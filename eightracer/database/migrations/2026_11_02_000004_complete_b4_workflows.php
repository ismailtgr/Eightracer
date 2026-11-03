<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_staff_grants', function (Blueprint $t) {
            $t->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $t->string('granted_email');
            $t->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->dateTime('granted_at');
            $t->dateTime('revoked_at')->nullable();
        });
        Schema::create('school_import_batches', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->string('source', 100);
            $t->string('mode', 20);
            $t->string('status', 24)->index();
            $t->char('fingerprint', 64)->unique();
            $t->char('request_fingerprint', 64)->index();
            $t->char('commit_digest', 64);
            $t->longText('plan')->nullable();
            $t->json('summary');
            $t->json('receipt')->nullable();
            $t->dateTime('expires_at');
            $t->dateTime('completed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('school_import_links', function (Blueprint $t) {
            $t->id();
            $t->string('source', 100);
            $t->string('module', 30);
            $t->string('external_key', 100);
            $t->string('record_table', 60);
            $t->unsignedBigInteger('record_id');
            $t->char('payload_hash', 64);
            $t->foreignUuid('last_batch_id')->constrained('school_import_batches')->restrictOnDelete();
            $t->unique(['source', 'module', 'external_key'], 'school_import_source_key');
            $t->timestamps();
        });
        Schema::create('student_profile_submissions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $t->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $t->longText('payload');
            $t->char('source_fingerprint', 64);
            $t->string('status', 20)->default('pending');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->dateTime('reviewed_at')->nullable();
            $t->timestamps();
            $t->index(['siswa_id', 'status']);
        });
        Schema::table('alumni_progress', fn (Blueprint $t) => $t->index(['siswa_id', 'is_current', 'tanggal_update', 'id'], 'progress_latest_student'));
        Schema::table('alumni_progress_beasiswa', fn (Blueprint $t) => $t->index(['beasiswa_id', 'status_beasiswa', 'alumni_progress_id'], 'award_program_status'));
        Schema::table('study_tracer', fn (Blueprint $t) => $t->index(['perguruan_tinggi_id', 'jalur_masuk'], 'tracer_university_path'));
    }

    public function down(): void
    {
        Schema::table('study_tracer', fn (Blueprint $t) => $t->dropIndex('tracer_university_path'));
        Schema::table('alumni_progress_beasiswa', fn (Blueprint $t) => $t->dropIndex('award_program_status'));
        Schema::table('alumni_progress', fn (Blueprint $t) => $t->dropIndex('progress_latest_student'));
        Schema::dropIfExists('student_profile_submissions');
        Schema::dropIfExists('school_import_links');
        Schema::dropIfExists('school_import_batches');
        Schema::dropIfExists('school_staff_grants');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (! Schema::hasColumn('users', 'full_name')) {
                $t->string('full_name')->nullable();
            }
            if (! Schema::hasColumn('users', 'google_id')) {
                $t->string('google_id')->nullable()->unique();
            }
            if (! Schema::hasColumn('users', 'photo_path')) {
                $t->string('photo_path', 500)->nullable();
            }
            $t->string('password')->nullable()->change();
        });
        Schema::table('siswa', function (Blueprint $t) {
            $t->unsignedBigInteger('user_id')->nullable()->change();
            $t->string('nisn')->nullable()->change();
            $t->string('jenis_kelamin')->nullable()->change();
            if (! Schema::hasColumn('siswa', 'registration_email')) {
                $t->string('registration_email')->nullable()->index();
            }
            if (! Schema::hasColumn('siswa', 'tanggal_masuk_sekolah')) {
                $t->date('tanggal_masuk_sekolah')->nullable();
            }
            if (! Schema::hasColumn('siswa', 'tanggal_lulus')) {
                $t->date('tanggal_lulus')->nullable();
            }
            if (! Schema::hasColumn('siswa', 'tahun_lulus')) {
                $t->unsignedSmallInteger('tahun_lulus')->nullable();
            }
            if (! Schema::hasColumn('siswa', 'bantuan_dikonfirmasi')) {
                $t->boolean('bantuan_dikonfirmasi')->default(false);
            }
            if (! Schema::hasColumn('siswa', 'prestasi_dikonfirmasi')) {
                $t->boolean('prestasi_dikonfirmasi')->default(false);
            }
            if (! Schema::hasColumn('siswa', 'organisasi_dikonfirmasi')) {
                $t->boolean('organisasi_dikonfirmasi')->default(false);
            }
            if (! Schema::hasColumn('siswa', 'karakter_dikonfirmasi')) {
                $t->boolean('karakter_dikonfirmasi')->default(false);
            }
            if (! Schema::hasColumn('siswa', 'izinkan_analitik')) {
                $t->boolean('izinkan_analitik')->default(false);
            }
            if (! Schema::hasColumn('siswa', 'referensi_izin_analitik')) {
                $t->string('referensi_izin_analitik', 500)->nullable();
            }
            if (! Schema::hasColumn('siswa', 'izin_analitik_at')) {
                $t->timestamp('izin_analitik_at')->nullable();
            }
        });
        Schema::create('auth_challenges', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('email');
            $t->string('purpose', 40);
            $t->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $t->foreignId('siswa_id')->nullable()->constrained('siswa')->cascadeOnDelete();
            $t->text('payload');
            $t->string('code_hash', 64);
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->dateTime('expires_at');
            $t->dateTime('verified_at')->nullable();
            $t->string('grant_hash', 64)->nullable();
            $t->dateTime('consumed_at')->nullable();
            $t->dateTime('created_at');
            $t->index(['email', 'purpose', 'created_at']);
        });
        Schema::create('private_attachments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $t->string('path', 500);
            $t->string('mime', 100);
            $t->string('original_name');
            $t->unsignedInteger('size');
            $t->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::table('prestasi_murid', fn (Blueprint $t) => $t->foreignId('attachment_id')->nullable()->constrained('private_attachments')->nullOnDelete());
        Schema::table('asesmen_karakteristik', function (Blueprint $t) {
            $t->string('gaya_belajar', 50)->nullable();
            $t->decimal('iq', 5, 2)->nullable();
            $t->boolean('is_current')->default(true);
            $t->unsignedBigInteger('supersedes_id')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });
        foreach (['asesmen_riasec', 'asesmen_multiple_intelligence'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->foreignId('asesmen_id')->nullable()->constrained('asesmen_karakteristik')->cascadeOnDelete());
        }
        Schema::create('program_eligibilitas', function (Blueprint $t) {
            $t->id();
            $t->string('nama');
            $t->text('deskripsi')->nullable();
            $t->json('bobot_prestasi')->nullable();
            $t->string('metode_prestasi', 30)->nullable();
            $t->json('bantuan_ids')->nullable();
            $t->string('mode_bantuan', 20)->nullable();
            $t->json('kriteria');
            $t->unsignedInteger('version')->default(1);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('hasil_program_eligibilitas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $t->foreignId('program_id')->constrained('program_eligibilitas')->restrictOnDelete();
            $t->unsignedInteger('program_version');
            $t->string('status', 40);
            $t->decimal('skor', 8, 4);
            $t->json('evaluasi');
            $t->string('source_fingerprint', 64);
            $t->timestamps();
        });
        Schema::table('basis_data_beasiswa', function (Blueprint $t) {
            $t->decimal('durasi', 5, 2)->nullable();
            $t->string('satuan_durasi', 15)->nullable();
            $t->string('periode_apply', 255)->nullable();
            $t->foreignId('program_eligibilitas_id')->nullable()->constrained('program_eligibilitas')->restrictOnDelete();
        });
        Schema::table('klaster_prodi', function (Blueprint $t) {
            $t->json('bobot_komponen')->nullable();
            $t->boolean('is_active')->default(true);
        });
        Schema::table('rekomendasi_prodi', function (Blueprint $t) {
            $t->uuid('batch_id')->nullable()->index();
            $t->string('status_review', 20)->default('pending');
            $t->string('source_fingerprint', 64)->nullable();
            $t->json('detail_penilaian')->nullable();
            $t->json('criteria_snapshot')->nullable();
            $t->text('catatan_admin')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
        });
        Schema::table('study_tracer', function (Blueprint $t) {
            $t->unsignedSmallInteger('tahun_masuk_pt')->nullable();
            $t->date('tanggal_masuk_pt')->nullable();
            $t->string('consent_reference', 500)->nullable();
            $t->timestamp('consent_at')->nullable();
            $t->foreignId('consent_by')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::table('alumni_progress', function (Blueprint $t) {
            $t->string('kategori_ekonomi', 30)->nullable();
            $t->string('referensi_ekonomi', 500)->nullable();
            $t->string('periode', 50)->nullable();
            $t->unsignedTinyInteger('semester')->nullable();
            $t->boolean('penerima_kipk')->nullable();
            $t->decimal('nominal_ukt', 15, 2)->nullable();
            $t->decimal('nominal_ipi', 15, 2)->nullable();
            $t->boolean('is_published')->default(false);
            $t->boolean('publish_financials')->default(false);
            $t->string('consent_reference', 500)->nullable();
            $t->timestamp('consent_at')->nullable();
            $t->foreignId('consent_by')->nullable()->constrained('users')->nullOnDelete();
            $t->boolean('is_current')->default(true);
            $t->unsignedSmallInteger('revision')->default(1);
            $t->unsignedBigInteger('supersedes_id')->nullable();
            $t->unique(['study_tracer_id', 'periode', 'revision'], 'progress_period_revision');
        });
        Schema::table('alumni_progress_beasiswa', fn (Blueprint $t) => $t->json('beasiswa_snapshot')->nullable());
        Schema::table('rekomendasi_beasiswa', fn (Blueprint $t) => $t->string('target_jenjang', 20)->nullable());
        Schema::create('published_cohort_summaries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('beasiswa_id')->constrained('basis_data_beasiswa')->restrictOnDelete();
            $t->unsignedSmallInteger('angkatan');
            $t->json('summary');
            $t->json('member_siswa_ids');
            $t->boolean('is_published')->default(false);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        // Forward-only: reverting these columns could orphan accounts or discard school history.
        throw new RuntimeException('Gunakan backup dan migrasi koreksi yang ditinjau untuk membatalkan perubahan ini.');
    }
};

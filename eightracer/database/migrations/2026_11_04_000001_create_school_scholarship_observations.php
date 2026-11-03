<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_scholarship_observations', function (Blueprint $t) {
            $t->id();
            $t->char('source_key', 64)->unique();
            $t->string('source_file_id', 64);
            $t->char('source_sha256', 64);
            $t->string('source_sheet', 100);
            $t->unsignedInteger('source_row');
            $t->string('source_column', 4);
            $t->unsignedSmallInteger('tahun_lulus')->index();
            $t->foreignId('siswa_id')->nullable()->constrained('siswa')->restrictOnDelete();
            $t->foreignId('study_tracer_id')->nullable()->constrained('study_tracer')->restrictOnDelete();
            $t->string('nama_kolom', 50);
            $t->string('nilai_sumber', 500);
            $t->string('kampus_sumber')->nullable();
            $t->string('prodi_sumber')->nullable();
            $t->string('jenis_keterangan', 40)->index();
            $t->boolean('indikator_kip')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_scholarship_observations');
    }
};

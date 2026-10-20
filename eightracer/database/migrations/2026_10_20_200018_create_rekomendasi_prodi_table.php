<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rekomendasi_prodi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->foreignId('klaster_id')->constrained('klaster_prodi')->restrictOnDelete();
            $table->foreignId('study_tracer_id')->nullable()->constrained('study_tracer')->nullOnDelete();
            $table->integer('semester');
            $table->decimal('nilai_rata_siswa', 5, 2)->nullable();
            $table->decimal('persentase_kesesuaian', 5, 2)->nullable();
            $table->text('alasan_rekomendasi')->nullable();
            $table->dateTime('tanggal_rekomendasi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rekomendasi_prodi');
    }
};

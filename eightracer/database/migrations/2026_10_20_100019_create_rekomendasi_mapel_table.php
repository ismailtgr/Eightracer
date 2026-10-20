<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rekomendasi_mapel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->foreignId('mata_pelajaran_id')->constrained('mata_pelajaran')->restrictOnDelete();
            $table->decimal('skor_rekomendasi', 8, 2);
            $table->string('tingkat_rekomendasi')->nullable();
            $table->text('alasan_rekomendasi')->nullable();
            $table->date('tanggal_rekomendasi');
            $table->string('tahun_ajaran');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rekomendasi_mapel');
    }
};

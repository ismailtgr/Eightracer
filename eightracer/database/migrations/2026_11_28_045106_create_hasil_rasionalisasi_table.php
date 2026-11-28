<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hasil_rasionalisasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->foreignId('program_studi_id')->constrained('program_studi')->cascadeOnDelete();
            $table->foreignId('periode_id')->nullable()
                ->constrained('periode_eligibilitas')->nullOnDelete();
            $table->unsignedTinyInteger('pilihan_ke');
            $table->decimal('skor_rapor', 8, 4)->nullable();
            $table->decimal('skor_mapel_pendukung', 8, 4)->nullable();
            $table->decimal('skor_tka', 8, 4)->nullable();
            $table->decimal('skor_prestasi', 8, 4)->nullable();
            $table->decimal('skor_total', 8, 4)->nullable();
            $table->decimal('peluang_persen', 5, 2)->nullable();
            $table->enum('kategori', ['Aman', 'Kompetitif', 'Berisiko'])->nullable();
            $table->timestamp('tanggal_kalkulasi')->useCurrent();
            $table->timestamps();

            $table->unique(['siswa_id', 'program_studi_id', 'periode_id', 'pilihan_ke'], 'hasil_rasionali_siswa_prodi_periode_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hasil_rasionalisasi');
    }
};
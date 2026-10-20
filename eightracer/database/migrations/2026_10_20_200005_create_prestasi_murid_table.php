<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prestasi_murid', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->string('jenis_prestasi');
            $table->string('nama_kompetisi');
            $table->string('bidang')->nullable();
            $table->string('tingkat')->nullable();
            $table->string('peringkat')->nullable();
            $table->decimal('bobot_persentase', 5, 2)->nullable();
            $table->string('status_verifikasi')->default('pending');
            $table->string('penyelenggara')->nullable();
            $table->integer('tahun')->nullable();
            $table->string('file_bukti_url', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prestasi_murid');
    }
};

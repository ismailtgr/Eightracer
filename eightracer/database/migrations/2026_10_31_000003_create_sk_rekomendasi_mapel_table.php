<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sk_rekomendasi_mapel', function (Blueprint $table) {
            $table->id();

            $table->foreignId('siswa_id')
                  ->unique()
                  ->constrained('siswa')
                  ->cascadeOnDelete();

            $table->foreignId('guru_bk_id')
                  ->nullable()
                  ->constrained('guru')
                  ->nullOnDelete();

            $table->string('tahun_ajaran');
            $table->string('nomor_sk')->nullable();

            $table->text('konten_surat')->nullable();

            $table->enum('status', ['draft', 'diterbitkan'])->default('draft');
            $table->date('tanggal_terbit')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sk_rekomendasi_mapel');
    }
};

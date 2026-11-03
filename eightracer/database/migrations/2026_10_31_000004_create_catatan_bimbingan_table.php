<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catatan_bimbingan', function (Blueprint $table) {
            $table->id();

            $table->foreignId('siswa_id')
                  ->constrained('siswa')
                  ->cascadeOnDelete();

            $table->foreignId('guru_bk_id')
                  ->constrained('guru')
                  ->cascadeOnDelete();

            $table->foreignId('mata_pelajaran_id')
                  ->nullable()
                  ->constrained('mata_pelajaran')
                  ->nullOnDelete();

            $table->text('konten');
            $table->text('strategi_belajar')->nullable();
            $table->unsignedSmallInteger('alokasi_jam_per_minggu')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catatan_bimbingan');
    }
};

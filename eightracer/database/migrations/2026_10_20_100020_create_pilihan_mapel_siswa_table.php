<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pilihan_mapel_siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->foreignId('mata_pelajaran_id')->constrained('mata_pelajaran')->restrictOnDelete();
            $table->string('tahun_ajaran');
            $table->string('tingkat_kelas');
            $table->string('status');
            $table->date('tanggal_pilihan')->nullable();
            $table->unique(['siswa_id', 'mata_pelajaran_id', 'tahun_ajaran'], 'pilihan_mapel_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pilihan_mapel_siswa');
    }
};

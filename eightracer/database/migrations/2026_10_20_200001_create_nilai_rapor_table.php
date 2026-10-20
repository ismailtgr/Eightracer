<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nilai_rapor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->foreignId('mata_pelajaran_id')->constrained('mata_pelajaran')->restrictOnDelete();
            $table->integer('semester');
            $table->decimal('nilai_akhir', 5, 2);
            $table->unique(['siswa_id', 'mata_pelajaran_id', 'semester'], 'nilai_rapor_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nilai_rapor');
    }
};

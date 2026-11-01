<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('minat_prodi_siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->unsignedTinyInteger('prioritas');            // 1 = pilihan 1, 2 = pilihan 2
            $table->string('nama_prodi');
            $table->string('perguruan_tinggi')->nullable();      // opsional
            $table->timestamps();

            $table->unique(['siswa_id', 'prioritas'], 'minat_prodi_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('minat_prodi_siswa');
    }
};

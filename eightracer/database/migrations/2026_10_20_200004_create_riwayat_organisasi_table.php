<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_organisasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->string('jenis_organisasi');
            $table->string('nama_organisasi');
            $table->string('jabatan')->nullable();
            $table->decimal('bobot_jabatan', 5, 2)->nullable();
            $table->string('periode_jabatan')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_organisasi');
    }
};

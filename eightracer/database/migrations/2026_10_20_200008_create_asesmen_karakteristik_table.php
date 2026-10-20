<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asesmen_karakteristik', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->string('tipe_kepribadian')->nullable();
            $table->string('kecerdasan_majemuk_dominan')->nullable();
            $table->string('kode_riasec', 10)->nullable();
            $table->dateTime('tanggal_asesmen');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asesmen_karakteristik');
    }
};

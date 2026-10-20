<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('klaster_prodi', function (Blueprint $table) {
            $table->id();
            $table->string('nama_klaster');
            $table->string('kode_riasec_relevan', 10)->nullable();
            $table->string('kecerdasan_relevan')->nullable();
            $table->text('deskripsi')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('klaster_prodi');
    }
};

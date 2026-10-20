<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_sosial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->string('jenis_media');
            $table->string('username')->nullable();
            $table->string('nomor_wa')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_sosial');
    }
};

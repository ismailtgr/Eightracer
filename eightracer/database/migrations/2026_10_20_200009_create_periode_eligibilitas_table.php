<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periode_eligibilitas', function (Blueprint $table) {
            $table->id();
            $table->string('tahun_ajaran')->unique();
            $table->integer('kuota_siswa');
            $table->boolean('is_active')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periode_eligibilitas');
    }
};

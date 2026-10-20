<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catatan_karakter', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->integer('semester');
            $table->string('jenis_catatan');
            $table->integer('poin_disiplin')->default(0);
            $table->text('deskripsi')->nullable();
            $table->dateTime('tanggal_pencatatan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catatan_karakter');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('nisn')->unique();
            $table->string('nis')->nullable();
            $table->string('nama_lengkap');
            $table->string('nama_panggilan')->nullable();
            $table->enum('jenis_kelamin', ['L', 'P']);
            $table->string('tempat_lahir')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('agama')->nullable();
            $table->string('golongan_darah')->nullable();
            $table->text('alamat_rumah')->nullable();
            $table->string('tinggal_bersama')->nullable();
            $table->integer('anak_ke')->nullable();
            $table->string('warna_favorit')->nullable();
            $table->string('makanan_favorit')->nullable();
            $table->string('bahasa_keseharian')->nullable();
            $table->string('permainan_online')->nullable();
            $table->string('permainan_offline')->nullable();
            $table->decimal('nilai_tka', 5, 2)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siswa');
    }
};

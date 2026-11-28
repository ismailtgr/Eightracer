<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_studi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('perguruan_tinggi_id')
                ->constrained('perguruan_tinggi')->cascadeOnDelete();
            $table->string('pddikti_prodi_id')->nullable()->unique();
            $table->string('nama_prodi');
            $table->string('kode_prodi')->nullable();
            $table->enum('jenjang', ['S1', 'D4', 'D3']);
            $table->string('akreditasi_prodi')->nullable();
            $table->foreignId('klaster_id')->nullable()
                ->constrained('klaster_prodi')->nullOnDelete();
            $table->integer('jumlah_mahasiswa')->nullable();
            $table->integer('daya_tampung_total')->nullable();
            $table->year('tahun_data')->nullable();
            $table->timestamps();

            $table->unique(['perguruan_tinggi_id', 'nama_prodi', 'jenjang']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_studi');
    }
};
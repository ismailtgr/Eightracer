<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buku_alumni', function (Blueprint $table) {
            $table->id();
            $table->string('tahun_lulusan');
            $table->string('judul_buku');
            $table->string('file_pdf_url', 500)->nullable();
            $table->string('thumbnail_cover_url', 500)->nullable();
            $table->boolean('is_published')->default(false);
            $table->date('tanggal_rilis')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buku_alumni');
    }
};

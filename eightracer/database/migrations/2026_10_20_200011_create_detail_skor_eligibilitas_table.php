<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detail_skor_eligibilitas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hasil_id')->unique()->constrained('hasil_eligibilitas')->cascadeOnDelete();
            $table->decimal('skor_raw_rapor', 8, 4)->nullable();
            $table->decimal('skor_norm_rapor', 8, 4)->nullable();
            $table->decimal('skor_raw_tka', 8, 4)->nullable();
            $table->decimal('skor_norm_tka', 8, 4)->nullable();
            $table->decimal('skor_raw_prestasi', 8, 4)->nullable();
            $table->decimal('skor_norm_prestasi', 8, 4)->nullable();
            $table->decimal('skor_raw_presensi', 8, 4)->nullable();
            $table->decimal('skor_norm_presensi', 8, 4)->nullable();
            $table->decimal('skor_raw_karakter', 8, 4)->nullable();
            $table->decimal('skor_norm_karakter', 8, 4)->nullable();
            $table->decimal('skor_raw_organisasi', 8, 4)->nullable();
            $table->decimal('skor_norm_organisasi', 8, 4)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_skor_eligibilitas');
    }
};

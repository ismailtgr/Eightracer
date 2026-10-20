<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rekomendasi_beasiswa_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rekomendasi_beasiswa_id')->constrained('rekomendasi_beasiswa')->cascadeOnDelete();
            $table->foreignId('beasiswa_id')->constrained('basis_data_beasiswa')->restrictOnDelete();
            $table->decimal('skor_kecocokan', 8, 4);
            $table->text('alasan_rekomendasi')->nullable();
            $table->boolean('is_recommended')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rekomendasi_beasiswa_detail');
    }
};

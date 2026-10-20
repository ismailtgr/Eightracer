<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aturan_rekomendasi_mapel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mata_pelajaran_id')->constrained('mata_pelajaran')->cascadeOnDelete();
            $table->string('indikator');
            $table->string('nilai_indikator');
            $table->decimal('bobot', 5, 2);
            $table->string('sumber_panduan')->nullable();
            $table->text('keterangan')->nullable();
            $table->boolean('aktif')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aturan_rekomendasi_mapel');
    }
};

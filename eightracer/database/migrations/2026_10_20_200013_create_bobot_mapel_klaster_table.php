<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bobot_mapel_klaster', function (Blueprint $table) {
            $table->id();
            $table->foreignId('klaster_id')->constrained('klaster_prodi')->cascadeOnDelete();
            $table->foreignId('mata_pelajaran_id')->constrained('mata_pelajaran')->restrictOnDelete();
            $table->decimal('bobot_persentase', 5, 2);
            $table->unique(['klaster_id', 'mata_pelajaran_id'], 'bobot_mapel_klaster_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bobot_mapel_klaster');
    }
};

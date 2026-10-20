<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cita_cita_siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->foreignId('cita_cita_id')->constrained('cita_cita')->restrictOnDelete();
            $table->integer('prioritas')->default(1);
            $table->unique(['siswa_id', 'cita_cita_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cita_cita_siswa');
    }
};

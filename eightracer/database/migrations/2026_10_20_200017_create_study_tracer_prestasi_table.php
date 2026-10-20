<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_tracer_prestasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_tracer_id')->constrained('study_tracer')->cascadeOnDelete();
            $table->foreignId('prestasi_murid_id')->constrained('prestasi_murid')->cascadeOnDelete();
            $table->unique(['study_tracer_id', 'prestasi_murid_id'], 'study_tracer_prestasi_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_tracer_prestasi');
    }
};

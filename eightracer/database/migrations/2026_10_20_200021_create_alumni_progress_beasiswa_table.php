<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alumni_progress_beasiswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumni_progress_id')->constrained('alumni_progress')->cascadeOnDelete();
            $table->foreignId('beasiswa_id')->constrained('basis_data_beasiswa')->restrictOnDelete();
            $table->enum('status_beasiswa', ['proses', 'diterima', 'ditolak'])->default('proses');
            $table->decimal('nominal_beasiswa', 15, 2)->nullable();
            $table->decimal('nominal_ukt', 15, 2)->nullable();
            $table->string('periode')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumni_progress_beasiswa');
    }
};

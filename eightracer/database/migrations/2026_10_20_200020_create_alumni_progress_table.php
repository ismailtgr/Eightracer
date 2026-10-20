<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alumni_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->foreignId('study_tracer_id')->nullable()->constrained('study_tracer')->nullOnDelete();
            $table->string('tahun_lulus');
            $table->string('status_pendidikan')->nullable();
            $table->decimal('ipk_terkini', 3, 2)->nullable();
            $table->date('tanggal_update');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumni_progress');
    }
};

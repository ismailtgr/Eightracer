<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hasil_eligibilitas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->foreignId('periode_id')->constrained('periode_eligibilitas')->cascadeOnDelete();
            $table->decimal('total_skor_akhir', 8, 4);
            $table->integer('peringkat')->nullable();
            $table->string('status_eligible')->nullable();
            $table->dateTime('tanggal_kalkulasi');
            $table->unique(['siswa_id', 'periode_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hasil_eligibilitas');
    }
};

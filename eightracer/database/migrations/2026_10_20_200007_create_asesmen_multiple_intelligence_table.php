<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asesmen_multiple_intelligence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->string('jenis_kecerdasan');
            $table->decimal('skor', 6, 2);
            $table->date('tanggal_asesmen');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asesmen_multiple_intelligence');
    }
};

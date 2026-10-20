<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asesmen_riasec', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->decimal('realistic_score', 6, 2)->default(0);
            $table->decimal('investigative_score', 6, 2)->default(0);
            $table->decimal('artistic_score', 6, 2)->default(0);
            $table->decimal('social_score', 6, 2)->default(0);
            $table->decimal('enterprising_score', 6, 2)->default(0);
            $table->decimal('conventional_score', 6, 2)->default(0);
            $table->string('kode_hasil', 10)->nullable();
            $table->date('tanggal_asesmen');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asesmen_riasec');
    }
};

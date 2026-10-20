<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kriteria_kelayakan_beasiswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beasiswa_id')->constrained('basis_data_beasiswa')->cascadeOnDelete();
            $table->string('nama_kriteria');
            $table->string('tipe_kriteria');
            $table->string('operator', 20);
            $table->string('nilai_kriteria');
            $table->decimal('bobot', 5, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kriteria_kelayakan_beasiswa');
    }
};

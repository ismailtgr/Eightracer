<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('snbp_prodi_tahun', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_studi_id')
                ->constrained('program_studi')->cascadeOnDelete();
            $table->year('tahun');
            $table->integer('daya_tampung_snbp')->nullable();
            $table->integer('peminat_snbp')->nullable();
            // Ambang lolos — HANYA diisi oleh Hitung Ulang Ambang dari Tracer,
            // tidak pernah oleh CSV maupun input manual daya tampung/peminat.
            $table->decimal('rata_rata_lolos', 5, 2)->nullable();
            $table->decimal('min_lolos', 5, 2)->nullable();
            $table->unsignedInteger('jumlah_sampel_alumni')->default(0);
            $table->string('sumber_ambang')->nullable();
            $table->timestamps();

            $table->unique(['program_studi_id', 'tahun']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('snbp_prodi_tahun');
    }
};
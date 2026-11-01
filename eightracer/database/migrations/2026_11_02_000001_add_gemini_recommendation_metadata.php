<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rekomendasi_beasiswa', function (Blueprint $table) {
            $table->string('ai_model')->nullable();
            $table->string('prompt_version')->nullable();
            $table->string('source_fingerprint', 64)->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
        });
        Schema::table('rekomendasi_beasiswa_detail', function (Blueprint $table) {
            $table->json('evaluasi_kriteria')->nullable();
            $table->json('beasiswa_snapshot')->nullable();
            $table->json('persiapan')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('rekomendasi_beasiswa_detail', fn (Blueprint $table) => $table->dropColumn(['evaluasi_kriteria', 'beasiswa_snapshot', 'persiapan']));
        Schema::table('rekomendasi_beasiswa', fn (Blueprint $table) => $table->dropColumn(['ai_model', 'prompt_version', 'source_fingerprint', 'reviewed_by', 'reviewed_at']));
    }
};

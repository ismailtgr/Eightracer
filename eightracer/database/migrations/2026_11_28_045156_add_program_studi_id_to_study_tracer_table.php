<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('study_tracer', function (Blueprint $table) {
            // FK ke master prodi; string program_studi lama dipertahankan
            // sebagai fallback untuk data alumni lama.
            $table->foreignId('program_studi_id')->nullable()
                ->after('program_studi')
                ->constrained('program_studi')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('study_tracer', function (Blueprint $table) {
            $table->dropForeign(['program_studi_id']);
            $table->dropColumn('program_studi_id');
        });
    }
};
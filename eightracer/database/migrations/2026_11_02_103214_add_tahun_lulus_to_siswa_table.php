<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('siswa', 'tahun_lulus')) {
            return; // B4 complete_prd_backend already owns this column.
        }
        Schema::table('siswa', function (Blueprint $table) {
            $table->string('tahun_lulus', 10)->nullable()->after('nisn');
        });
    }

    public function down(): void
    {
        // Shared with the earlier B4 migration: never drop its column here.
    }
};
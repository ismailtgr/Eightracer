<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mata_pelajaran', function (Blueprint $table) {
            if (!Schema::hasColumn('mata_pelajaran', 'topik_utama')) {
                $table->json('topik_utama')->nullable()->after('deskripsi');
            }
        });

        Schema::table('rekomendasi_mapel', function (Blueprint $table) {
            if (!Schema::hasColumn('rekomendasi_mapel', 'fokus_penguatan_materi')) {
                $table->text('fokus_penguatan_materi')->nullable()->after('strategi_belajar');
            }
            if (!Schema::hasColumn('rekomendasi_mapel', 'catatan_evaluasi_akademik')) {
                $table->text('catatan_evaluasi_akademik')->nullable()->after('fokus_penguatan_materi');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rekomendasi_mapel', function (Blueprint $table) {
            $table->dropColumn(['fokus_penguatan_materi', 'catatan_evaluasi_akademik']);
        });

        Schema::table('mata_pelajaran', function (Blueprint $table) {
            $table->dropColumn('topik_utama');
        });
    }
};

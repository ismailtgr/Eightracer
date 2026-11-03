<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rekomendasi_mapel', function (Blueprint $table) {
            $table->enum('status', ['draft', 'dikunci', 'diterbitkan'])
                  ->default('draft')
                  ->after('tahun_ajaran');

            $table->foreignId('dikunci_oleh')
                  ->nullable()
                  ->after('status')
                  ->constrained('guru')
                  ->nullOnDelete();

            $table->timestamp('dikunci_pada')->nullable()->after('dikunci_oleh');

            $table->decimal('probabilitas_penguasaan', 5, 2)->nullable()->after('dikunci_pada');

            $table->text('strategi_belajar')->nullable()->after('probabilitas_penguasaan');
        });
    }

    public function down(): void
    {
        Schema::table('rekomendasi_mapel', function (Blueprint $table) {
            $table->dropForeign(['dikunci_oleh']);
            $table->dropColumn(['status', 'dikunci_oleh', 'dikunci_pada', 'probabilitas_penguasaan', 'strategi_belajar']);
        });
    }
};

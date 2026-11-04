<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('rekomendasi_beasiswa', 'revision')) {
            Schema::table('rekomendasi_beasiswa', fn (Blueprint $table) => $table->unsignedInteger('revision')->default(1));
        }
        foreach (['is_hidden', 'waktu_disarankan', 'deleted_at'] as $column) {
            if (Schema::hasColumn('rekomendasi_beasiswa_detail', $column)) continue;
            Schema::table('rekomendasi_beasiswa_detail', function (Blueprint $table) use ($column) {
                if ($column === 'is_hidden') $table->boolean('is_hidden')->default(false);
                if ($column === 'waktu_disarankan') $table->string('waktu_disarankan', 180)->nullable();
                if ($column === 'deleted_at') $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::table('rekomendasi_beasiswa_detail', fn (Blueprint $table) => $table->dropColumn(['is_hidden', 'waktu_disarankan', 'deleted_at']));
        Schema::table('rekomendasi_beasiswa', fn (Blueprint $table) => $table->dropColumn('revision'));
    }
};

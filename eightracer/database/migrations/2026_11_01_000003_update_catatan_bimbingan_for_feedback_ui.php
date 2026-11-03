<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catatan_bimbingan', function (Blueprint $table) {
            $table->text('konten')->nullable()->change();

            if (!Schema::hasColumn('catatan_bimbingan', 'status')) {
                $table->enum('status', ['tersimpan', 'dilewati'])
                      ->default('tersimpan')
                      ->after('alokasi_jam_per_minggu');
            }
        });
    }

    public function down(): void
    {
        Schema::table('catatan_bimbingan', function (Blueprint $table) {
            if (Schema::hasColumn('catatan_bimbingan', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
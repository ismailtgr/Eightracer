<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guru', function (Blueprint $table) {
            if (!Schema::hasColumn('guru', 'foto_profil')) {
                $table->string('foto_profil')->nullable()->after('jabatan');
            }
        });
    }

    public function down(): void
    {
        Schema::table('guru', function (Blueprint $table) {
            if (Schema::hasColumn('guru', 'foto_profil')) {
                $table->dropColumn('foto_profil');
            }
        });
    }
};

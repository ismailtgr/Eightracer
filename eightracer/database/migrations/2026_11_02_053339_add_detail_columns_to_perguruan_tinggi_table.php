<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('perguruan_tinggi', function (Blueprint $table) {
            $table->string('nama_singkat')->nullable()->after('nama_pt');
            $table->string('kota')->nullable()->after('jenis_pt');
            $table->string('provinsi')->nullable()->after('kota');
            $table->string('akreditasi')->nullable()->after('provinsi');
            $table->string('telepon')->nullable()->after('akreditasi');
            $table->string('email')->nullable()->after('telepon');
            $table->text('alamat')->nullable()->after('email');
            $table->string('kode_pos')->nullable()->after('alamat');
        });
    }

    public function down(): void
    {
        Schema::table('perguruan_tinggi', function (Blueprint $table) {
            $table->dropColumn([
                'nama_singkat', 'kota', 'provinsi', 'akreditasi',
                'telepon', 'email', 'alamat', 'kode_pos',
            ]);
        });
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saudara_tiri', function (Blueprint $table) {
            $cols = array_intersect(
                ['nama', 'jenis_kelamin', 'hubungan', 'usia', 'keterangan'],
                Schema::getColumnListing('saudara_tiri')
            );
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
            if (!Schema::hasColumn('saudara_tiri', 'status')) {
                $table->string('status')->default('Tidak')->after('siswa_id');
            }
        });

        Schema::table('prestasi_murid', function (Blueprint $table) {
            $cols = array_intersect(
                ['bidang', 'peringkat', 'bobot_persentase', 'status_verifikasi', 'penyelenggara', 'tahun', 'file_bukti_url'],
                Schema::getColumnListing('prestasi_murid')
            );
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }

    public function down(): void
    {
        //
    }
};

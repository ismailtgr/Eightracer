<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_tracer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->foreignId('perguruan_tinggi_id')->constrained('perguruan_tinggi')->restrictOnDelete();
            $table->foreignId('buku_alumni_id')->nullable()->constrained('buku_alumni')->nullOnDelete();
            $table->string('strata_pendidikan');
            $table->string('program_studi');
            $table->string('jalur_masuk');
            $table->string('sub_jalur_mandiri')->nullable();
            $table->integer('pilihan_ke')->nullable();
            $table->enum('status_penerimaan', ['menunggu', 'diterima', 'tidak_diterima'])->default('menunggu');
            $table->float('nilai_rapor_pendaftaran')->nullable();
            $table->float('skor_utbk')->nullable();
            $table->string('kontak_wa_terbaru')->nullable();
            $table->boolean('is_pelamar_kipk')->default(false);
            $table->boolean('izinkan_tampil_di_buku')->default(false);
            $table->date('tanggal_pengisian')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_tracer');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orang_tua', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->enum('jenis_orang_tua', ['ayah', 'ibu']);
            $table->string('nama');
            $table->enum('kondisi', ['hidup', 'meninggal', 'bercerai'])->nullable();
            $table->string('pekerjaan')->nullable();
            $table->decimal('penghasilan', 15, 2)->nullable();
            $table->text('alamat')->nullable();
            $table->string('kontak_wa')->nullable();
            $table->unique(['siswa_id', 'jenis_orang_tua']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orang_tua');
    }
};

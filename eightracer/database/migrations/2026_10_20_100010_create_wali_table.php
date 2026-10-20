<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wali', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->unique()->constrained('siswa')->cascadeOnDelete();
            $table->string('nama');
            $table->string('hubungan')->nullable();
            $table->string('pekerjaan')->nullable();
            $table->decimal('penghasilan', 15, 2)->nullable();
            $table->text('alamat')->nullable();
            $table->string('kontak_wa')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wali');
    }
};

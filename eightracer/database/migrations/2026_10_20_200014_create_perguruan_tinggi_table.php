<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perguruan_tinggi', function (Blueprint $table) {
            $table->id();
            $table->string('nama_pt');
            $table->string('jenis_pt')->nullable();
            $table->string('kode_pt')->nullable()->unique();
            $table->json('lokasi')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perguruan_tinggi');
    }
};

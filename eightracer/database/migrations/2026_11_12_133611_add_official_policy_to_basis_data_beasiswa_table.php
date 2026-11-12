<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('basis_data_beasiswa', fn(Blueprint $t) => $t->json('official_policy')->nullable()); }
 public function down(): void { Schema::table('basis_data_beasiswa', fn(Blueprint $t) => $t->dropColumn('official_policy')); }
};

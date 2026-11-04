<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('school_provisioned_at')->nullable();
            $table->foreignId('school_provisioned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('school_password_changed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['school_provisioned_by']);
            $table->dropColumn(['school_provisioned_at', 'school_provisioned_by', 'school_password_changed_at']);
        });
    }
};

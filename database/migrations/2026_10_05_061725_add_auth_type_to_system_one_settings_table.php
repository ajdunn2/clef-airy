<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('system_one_settings', function (Blueprint $table) {
            $table->string('auth_type')->default('basic');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_one_settings', function (Blueprint $table) {
            $table->dropColumn('auth_type');
        });
    }
};

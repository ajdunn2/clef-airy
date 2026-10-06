<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('system_one_settings', function (Blueprint $table) {
            $table->string('model')->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('system_one_settings')->whereNull('model')->update(['model' => '']);

        Schema::table('system_one_settings', function (Blueprint $table) {
            $table->string('model')->default('clef-flash')->change();
        });
    }
};

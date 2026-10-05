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
        Schema::create('saved_calls', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('method');
            $table->string('path');
            $table->string('model')->nullable();
            $table->string('body_mode');
            $table->text('state')->nullable();
            $table->json('questions')->nullable();
            $table->text('body')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saved_calls');
    }
};

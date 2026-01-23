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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('system_id');
            $table->string('name');
            $table->string('api_key')->unique();
            $table->unsignedInteger('width_px');
            $table->unsignedInteger('height_px');
            $table->boolean('can_screenshot')->default(false);
            $table->timestamps();

            $table->index('system_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};

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
        Schema::create('client_type_command', function (Blueprint $table) {
            $table->foreignId('client_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('command_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['client_type_id', 'command_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_type_command');
    }
};

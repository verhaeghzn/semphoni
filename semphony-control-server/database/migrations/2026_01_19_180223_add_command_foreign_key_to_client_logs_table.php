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
        Schema::table('client_logs', function (Blueprint $table) {
            $table->foreign('command_id')
                ->references('id')
                ->on('commands')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_logs', function (Blueprint $table) {
            $table->dropForeign(['command_id']);
        });
    }
};

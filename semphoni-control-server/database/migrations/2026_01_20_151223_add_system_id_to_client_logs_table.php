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
        Schema::table('client_logs', function (Blueprint $table) {
            $table->foreignId('system_id')
                ->nullable()
                ->after('client_id')
                ->constrained()
                ->cascadeOnDelete();
        });

        DB::statement('
            UPDATE client_logs
            SET system_id = (
                SELECT system_id
                FROM clients
                WHERE clients.id = client_logs.client_id
            )
        ');

        Schema::table('client_logs', function (Blueprint $table) {
            $table->index(['system_id', 'id']);
            $table->index(['client_id', 'id']);
            $table->dropIndex(['client_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_logs', function (Blueprint $table) {
            $table->dropIndex(['system_id', 'id']);
            $table->dropIndex(['client_id', 'id']);
            $table->index(['client_id', 'created_at']);

            $table->dropForeign(['system_id']);
            $table->dropColumn('system_id');
        });
    }
};

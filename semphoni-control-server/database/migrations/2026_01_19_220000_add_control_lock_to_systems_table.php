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
        Schema::table('systems', function (Blueprint $table) {
            $table
                ->foreignId('control_locked_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->after('description');

            $table
                ->timestamp('control_locked_until')
                ->nullable()
                ->after('control_locked_by_user_id');

            $table->index('control_locked_by_user_id');
            $table->index('control_locked_until');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('systems', function (Blueprint $table) {
            $table->dropIndex(['control_locked_until']);
            $table->dropIndex(['control_locked_by_user_id']);

            $table->dropConstrainedForeignId('control_locked_by_user_id');
            $table->dropColumn('control_locked_until');
        });
    }
};


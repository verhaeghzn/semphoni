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
        Schema::table('clients', function (Blueprint $table) {
            $table->longText('last_screenshot_png_base64')->nullable()->after('can_screenshot');
            $table->timestamp('last_screenshot_taken_at')->nullable()->after('last_screenshot_png_base64');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'last_screenshot_png_base64',
                'last_screenshot_taken_at',
            ]);
        });
    }
};

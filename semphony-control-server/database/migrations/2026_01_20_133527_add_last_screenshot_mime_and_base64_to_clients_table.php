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
            $table->string('last_screenshot_mime')->nullable()->after('last_screenshot_png_base64');
            $table->longText('last_screenshot_base64')->nullable()->after('last_screenshot_mime');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'last_screenshot_mime',
                'last_screenshot_base64',
            ]);
        });
    }
};

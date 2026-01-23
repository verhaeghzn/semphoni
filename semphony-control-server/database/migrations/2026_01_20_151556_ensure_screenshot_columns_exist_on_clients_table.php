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
        $hasPngBase64 = Schema::hasColumn('clients', 'last_screenshot_png_base64');
        $hasTakenAt = Schema::hasColumn('clients', 'last_screenshot_taken_at');
        $hasMime = Schema::hasColumn('clients', 'last_screenshot_mime');
        $hasBase64 = Schema::hasColumn('clients', 'last_screenshot_base64');

        if ($hasPngBase64 && $hasTakenAt && $hasMime && $hasBase64) {
            return;
        }

        Schema::table('clients', function (Blueprint $table) use ($hasPngBase64, $hasTakenAt, $hasMime, $hasBase64): void {
            if (! $hasPngBase64) {
                $table->longText('last_screenshot_png_base64')->nullable();
            }

            if (! $hasTakenAt) {
                $table->timestamp('last_screenshot_taken_at')->nullable();
            }

            if (! $hasMime) {
                $table->string('last_screenshot_mime')->nullable();
            }

            if (! $hasBase64) {
                $table->longText('last_screenshot_base64')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $columnsToDrop = array_values(array_filter([
            Schema::hasColumn('clients', 'last_screenshot_png_base64') ? 'last_screenshot_png_base64' : null,
            Schema::hasColumn('clients', 'last_screenshot_taken_at') ? 'last_screenshot_taken_at' : null,
            Schema::hasColumn('clients', 'last_screenshot_mime') ? 'last_screenshot_mime' : null,
            Schema::hasColumn('clients', 'last_screenshot_base64') ? 'last_screenshot_base64' : null,
        ]));

        if ($columnsToDrop === []) {
            return;
        }

        Schema::table('clients', function (Blueprint $table) use ($columnsToDrop): void {
            $table->dropColumn($columnsToDrop);
        });
    }
};

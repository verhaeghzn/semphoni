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
        Schema::create('client_screenshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('mime')->nullable();
            $table->longText('base64')->nullable();
            $table->timestamp('taken_at')->nullable();
            $table->timestamps();
        });

        if (! Schema::hasTable('clients')) {
            return;
        }

        $hasLegacyPngBase64 = Schema::hasColumn('clients', 'last_screenshot_png_base64');
        $hasLegacyTakenAt = Schema::hasColumn('clients', 'last_screenshot_taken_at');
        $hasMime = Schema::hasColumn('clients', 'last_screenshot_mime');
        $hasBase64 = Schema::hasColumn('clients', 'last_screenshot_base64');

        if (! $hasLegacyPngBase64 && ! $hasLegacyTakenAt && ! $hasMime && ! $hasBase64) {
            return;
        }

        DB::table('clients')
            ->select([
                'id',
                ...($hasMime ? ['last_screenshot_mime'] : []),
                ...($hasBase64 ? ['last_screenshot_base64'] : []),
                ...($hasLegacyPngBase64 ? ['last_screenshot_png_base64'] : []),
                ...($hasLegacyTakenAt ? ['last_screenshot_taken_at'] : []),
            ])
            ->orderBy('id')
            ->chunkById(100, function ($clients) use ($hasMime, $hasBase64, $hasLegacyPngBase64, $hasLegacyTakenAt): void {
                foreach ($clients as $client) {
                    $base64 = null;
                    $mime = null;

                    if ($hasBase64 && is_string($client->last_screenshot_base64 ?? null) && $client->last_screenshot_base64 !== '') {
                        $base64 = $client->last_screenshot_base64;
                        $mime = $hasMime && is_string($client->last_screenshot_mime ?? null) && $client->last_screenshot_mime !== ''
                            ? $client->last_screenshot_mime
                            : null;
                    } elseif ($hasLegacyPngBase64 && is_string($client->last_screenshot_png_base64 ?? null) && $client->last_screenshot_png_base64 !== '') {
                        $base64 = $client->last_screenshot_png_base64;
                        $mime = 'image/png';
                    }

                    if (! is_string($base64) || $base64 === '') {
                        continue;
                    }

                    $takenAt = $hasLegacyTakenAt ? ($client->last_screenshot_taken_at ?? null) : null;

                    DB::table('client_screenshots')->updateOrInsert(
                        ['client_id' => $client->id],
                        [
                            'mime' => $mime,
                            'base64' => $base64,
                            'taken_at' => $takenAt,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                    );
                }
            });

        $columnsToDrop = array_values(array_filter([
            $hasLegacyPngBase64 ? 'last_screenshot_png_base64' : null,
            $hasLegacyTakenAt ? 'last_screenshot_taken_at' : null,
            $hasMime ? 'last_screenshot_mime' : null,
            $hasBase64 ? 'last_screenshot_base64' : null,
        ]));

        if ($columnsToDrop !== []) {
            Schema::table('clients', function (Blueprint $table) use ($columnsToDrop): void {
                $table->dropColumn($columnsToDrop);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('clients')) {
            $hasLegacyPngBase64 = Schema::hasColumn('clients', 'last_screenshot_png_base64');
            $hasLegacyTakenAt = Schema::hasColumn('clients', 'last_screenshot_taken_at');
            $hasMime = Schema::hasColumn('clients', 'last_screenshot_mime');
            $hasBase64 = Schema::hasColumn('clients', 'last_screenshot_base64');

            Schema::table('clients', function (Blueprint $table) use ($hasLegacyPngBase64, $hasLegacyTakenAt, $hasMime, $hasBase64): void {
                if (! $hasLegacyPngBase64) {
                    $table->longText('last_screenshot_png_base64')->nullable()->after('can_screenshot');
                }

                if (! $hasLegacyTakenAt) {
                    $table->timestamp('last_screenshot_taken_at')->nullable()->after('last_screenshot_png_base64');
                }

                if (! $hasMime) {
                    $table->string('last_screenshot_mime')->nullable()->after('last_screenshot_taken_at');
                }

                if (! $hasBase64) {
                    $table->longText('last_screenshot_base64')->nullable()->after('last_screenshot_mime');
                }
            });

            if (Schema::hasTable('client_screenshots')) {
                DB::table('client_screenshots')
                    ->select(['client_id', 'mime', 'base64', 'taken_at'])
                    ->orderBy('id')
                    ->chunkById(100, function ($screenshots): void {
                        foreach ($screenshots as $screenshot) {
                            $mime = is_string($screenshot->mime ?? null) ? $screenshot->mime : null;
                            $base64 = is_string($screenshot->base64 ?? null) ? $screenshot->base64 : null;

                            DB::table('clients')
                                ->where('id', $screenshot->client_id)
                                ->update([
                                    'last_screenshot_mime' => $mime,
                                    'last_screenshot_base64' => $base64,
                                    'last_screenshot_png_base64' => $mime === 'image/png' ? $base64 : null,
                                    'last_screenshot_taken_at' => $screenshot->taken_at,
                                ]);
                        }
                    });
            }
        }

        Schema::dropIfExists('client_screenshots');
    }
};

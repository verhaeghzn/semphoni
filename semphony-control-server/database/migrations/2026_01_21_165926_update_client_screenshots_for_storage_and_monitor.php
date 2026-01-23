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
        if (! Schema::hasTable('client_screenshots')) {
            return;
        }

        // SQLite stores UNIQUE constraints as autoindexes and cannot reliably drop
        // the original unique(client_id) created in the initial migration. Rebuild.
        if (DB::getDriverName() === 'sqlite') {
            Schema::create('client_screenshots_tmp', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('client_id')->constrained()->cascadeOnDelete();
                $table->unsignedTinyInteger('monitor_nr')->default(1);
                $table->string('mime')->nullable();
                $table->string('storage_disk')->default('local');
                $table->string('storage_path')->nullable();
                $table->unsignedBigInteger('bytes')->nullable();
                $table->string('sha256', 64)->nullable();
                $table->timestamp('taken_at')->nullable();
                $table->timestamps();

                $table->unique(['client_id', 'monitor_nr'], 'client_screenshots_client_monitor_unique');
            });

            $rows = DB::table('client_screenshots')
                ->select(['id', 'client_id', 'mime', 'taken_at', 'created_at', 'updated_at'])
                ->orderBy('id')
                ->get();

            foreach ($rows as $row) {
                DB::table('client_screenshots_tmp')->insert([
                    'id' => $row->id,
                    'client_id' => $row->client_id,
                    'monitor_nr' => 1,
                    'mime' => $row->mime,
                    'storage_disk' => 'local',
                    'storage_path' => null,
                    'bytes' => null,
                    'sha256' => null,
                    'taken_at' => $row->taken_at,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }

            Schema::drop('client_screenshots');
            Schema::rename('client_screenshots_tmp', 'client_screenshots');

            return;
        }

        Schema::table('client_screenshots', function (Blueprint $table): void {
            if (! Schema::hasColumn('client_screenshots', 'monitor_nr')) {
                $table->unsignedTinyInteger('monitor_nr')->default(1)->after('client_id');
            }

            if (! Schema::hasColumn('client_screenshots', 'storage_disk')) {
                $table->string('storage_disk')->default('local')->after('mime');
            }

            if (! Schema::hasColumn('client_screenshots', 'storage_path')) {
                $table->string('storage_path')->nullable()->after('storage_disk');
            }

            if (! Schema::hasColumn('client_screenshots', 'bytes')) {
                $table->unsignedBigInteger('bytes')->nullable()->after('storage_path');
            }

            if (! Schema::hasColumn('client_screenshots', 'sha256')) {
                $table->string('sha256', 64)->nullable()->after('bytes');
            }
        });

        // Convert unique(client_id) -> unique(client_id, monitor_nr)
        $newUniqueName = 'client_screenshots_client_monitor_unique';

        // Drop the unique constraint on client_id if it exists
        // We need to find the actual index name first, as Laravel may have created it with a different name
        if (DB::getDriverName() === 'mysql') {
            $indexes = DB::select("
                SELECT INDEX_NAME
                FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'client_screenshots'
                AND COLUMN_NAME = 'client_id'
                AND NON_UNIQUE = 0
            ");
            
            foreach ($indexes as $index) {
                // Drop each unique index on client_id that we find
                DB::statement("ALTER TABLE `client_screenshots` DROP INDEX `{$index->INDEX_NAME}`");
            }
        } else {
            // For non-MySQL databases, try to drop using Laravel's method
            Schema::table('client_screenshots', function (Blueprint $table): void {
                try {
                    $table->dropUnique(['client_id']);
                } catch (\Exception $e) {
                    // Index doesn't exist, continue
                }
            });
        }

        // Create the new unique constraint
        Schema::table('client_screenshots', function (Blueprint $table) use ($newUniqueName): void {
            $table->unique(['client_id', 'monitor_nr'], $newUniqueName);
        });

        // Drop base64 storage (artifacts must not live in DB).
        if (Schema::hasColumn('client_screenshots', 'base64')) {
            Schema::table('client_screenshots', function (Blueprint $table): void {
                $table->dropColumn('base64');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('client_screenshots')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            // Best-effort down: keep only monitor 1.
            if (Schema::hasColumn('client_screenshots', 'monitor_nr')) {
                DB::table('client_screenshots')
                    ->where('monitor_nr', '!=', 1)
                    ->delete();
            }

            Schema::create('client_screenshots_tmp', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('client_id')->constrained()->cascadeOnDelete()->unique();
                $table->string('mime')->nullable();
                $table->longText('base64')->nullable();
                $table->timestamp('taken_at')->nullable();
                $table->timestamps();
            });

            $rows = DB::table('client_screenshots')
                ->select(['id', 'client_id', 'mime', 'taken_at', 'created_at', 'updated_at'])
                ->orderBy('id')
                ->get();

            foreach ($rows as $row) {
                DB::table('client_screenshots_tmp')->insert([
                    'id' => $row->id,
                    'client_id' => $row->client_id,
                    'mime' => $row->mime,
                    'base64' => null,
                    'taken_at' => $row->taken_at,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }

            Schema::drop('client_screenshots');
            Schema::rename('client_screenshots_tmp', 'client_screenshots');

            return;
        }

        // Best-effort down: keep only monitor 1 to allow unique(client_id).
        if (Schema::hasColumn('client_screenshots', 'monitor_nr')) {
            DB::table('client_screenshots')
                ->where('monitor_nr', '!=', 1)
                ->delete();
        }

        Schema::table('client_screenshots', function (Blueprint $table): void {
            $table->dropUnique('client_screenshots_client_monitor_unique');
            $table->unique('client_id', 'client_screenshots_client_id_unique');
        });

        Schema::table('client_screenshots', function (Blueprint $table): void {
            if (! Schema::hasColumn('client_screenshots', 'base64')) {
                $table->longText('base64')->nullable()->after('mime');
            }

            if (Schema::hasColumn('client_screenshots', 'sha256')) {
                $table->dropColumn('sha256');
            }

            if (Schema::hasColumn('client_screenshots', 'bytes')) {
                $table->dropColumn('bytes');
            }

            if (Schema::hasColumn('client_screenshots', 'storage_path')) {
                $table->dropColumn('storage_path');
            }

            if (Schema::hasColumn('client_screenshots', 'storage_disk')) {
                $table->dropColumn('storage_disk');
            }

            if (Schema::hasColumn('client_screenshots', 'monitor_nr')) {
                $table->dropColumn('monitor_nr');
            }
        });
    }
};

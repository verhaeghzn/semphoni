<?php

use App\Enums\ActionType;
use App\Models\ClientType;
use App\Models\Command;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $command = Command::query()->updateOrCreate(
            ['name' => 'get_metrics'],
            [
                'action_type' => ActionType::Request,
                'description' => 'Fetch current metrics from the client.',
            ],
        );

        $tescanClientTypeId = ClientType::query()
            ->where('slug', 'tescan_sem')
            ->value('id');

        if (! is_int($tescanClientTypeId)) {
            return;
        }

        $now = now();

        DB::table('client_type_command')->insertOrIgnore([
            'client_type_id' => $tescanClientTypeId,
            'command_id' => $command->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('clients')
            ->select(['id'])
            ->where('client_type_id', $tescanClientTypeId)
            ->orderBy('id')
            ->chunkById(500, function ($clients) use ($command, $now): void {
                $rows = [];

                foreach ($clients as $client) {
                    $rows[] = [
                        'client_id' => $client->id,
                        'command_id' => $command->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows === []) {
                    return;
                }

                DB::table('client_command')->insertOrIgnore($rows);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $commandId = Command::query()
            ->where('name', 'get_metrics')
            ->value('id');

        if (! is_int($commandId)) {
            return;
        }

        DB::table('client_type_command')->where('command_id', $commandId)->delete();
        DB::table('client_command')->where('command_id', $commandId)->delete();
        DB::table('commands')->where('id', $commandId)->delete();
    }
};

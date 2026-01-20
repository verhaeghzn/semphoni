<?php

use App\Models\Client;
use App\Models\ClientType;
use App\Models\Command;

test('get_metrics is attached to tescan client type and existing tescan clients', function () {
    $tescanClientType = ClientType::factory()->create([
        'name' => 'TESCAN SEM',
        'slug' => 'tescan_sem',
    ]);

    $tescanClient = Client::factory()->create([
        'client_type_id' => $tescanClientType->id,
    ]);

    Command::query()->where('name', 'get_metrics')->delete();

    $migration = require database_path('migrations/2026_01_20_163520_add_get_metrics_command_to_tescan_clients.php');
    $migration->up();

    $command = Command::query()->where('name', 'get_metrics')->firstOrFail();

    expect($tescanClientType->fresh()->commands()->whereKey($command->id)->exists())->toBeTrue();
    expect($tescanClient->fresh()->commands()->whereKey($command->id)->exists())->toBeTrue();
});


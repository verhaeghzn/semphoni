<?php

use App\Enums\LogDirection;
use App\Models\Client;
use App\Models\ClientLog;
use App\Models\System;
use App\Models\User;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('logs page groups outbound and inbound by correlation id', function () {
    /** @var User $user */
    $user = User::factory()->create();
    actingAs($user);

    $system = System::factory()->create(['name' => 'System A']);
    $client = Client::factory()->create([
        'system_id' => $system->id,
        'name' => 'Client A',
    ]);

    $correlationId = '11111111-2222-3333-4444-555555555555';

    ClientLog::query()->create([
        'client_id' => $client->id,
        'direction' => LogDirection::Outbound,
        'command_id' => null,
        'summary' => 'Dispatched command ('.$correlationId.')',
        'payload' => [
            'event' => 'server-command',
            'channel' => 'presence-client.'.$client->id,
            'data' => [
                'correlation_id' => $correlationId,
                'command_name' => 'clickButton',
                'payload' => [
                    'button_name' => 'acquire',
                ],
            ],
        ],
    ]);

    ClientLog::query()->create([
        'client_id' => $client->id,
        'direction' => LogDirection::Inbound,
        'command_id' => null,
        'summary' => 'Command result ('.$correlationId.')',
        'payload' => [
            'event' => 'client-command-result',
            'channel' => 'presence-client.'.$client->id,
            'data' => [
                'correlation_id' => $correlationId,
                'command_name' => 'clickButton',
                'payload' => [
                    'button_name' => 'acquire',
                ],
                'ok' => true,
                'message' => 'done',
            ],
        ],
    ]);

    get('/logs')
        ->assertOk()
        ->assertSee($correlationId)
        ->assertSee('acquire')
        ->assertSee('done');
});


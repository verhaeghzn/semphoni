<?php

use App\Models\Client;
use App\Models\User;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use App\Enums\LogDirection;
use App\Models\ClientLog;

test('clients index shows install button', function () {
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($user);

    Client::factory()->create([
        'name' => 'Client A',
    ]);

    get('/clients')
        ->assertOk()
        ->assertSee('Install');
});

test('clients index hides install button for online clients', function () {
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($user);

    $client = Client::factory()->create([
        'name' => 'Online Client',
    ]);

    ClientLog::query()->create([
        'client_id' => $client->id,
        'direction' => LogDirection::Inbound,
        'summary' => 'Heartbeat',
        'payload' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    get('/clients')
        ->assertOk()
        ->assertDontSee('Install');
});


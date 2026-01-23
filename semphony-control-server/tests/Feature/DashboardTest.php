<?php

use App\Enums\LogDirection;
use App\Models\Client;
use App\Models\ClientLog;
use App\Models\System;
use App\Models\User;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('guests are redirected to the login page', function () {
    get('/dashboard')->assertRedirect('/login');
});

test('authenticated users can visit the dashboard', function () {
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($user);

    get('/dashboard')->assertOk();
});

test('dashboard lists systems and their clients', function () {
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($user);

    $system = System::factory()->create(['name' => 'System A']);
    $client = Client::factory()->create([
        'system_id' => $system->id,
        'name' => 'Client A',
    ]);

    get('/dashboard')
        ->assertOk()
        ->assertSee('System A')
        ->assertSee('Client A')
        ->assertSee('Control');
});

test('dashboard shows active and inactive client indicators', function () {
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($user);

    $system = System::factory()->create();

    $inactiveClient = Client::factory()->create([
        'system_id' => $system->id,
        'name' => 'Inactive Client',
    ]);

    $activeClient = Client::factory()->create([
        'system_id' => $system->id,
        'name' => 'Active Client',
    ]);

    ClientLog::query()->create([
        'client_id' => $activeClient->id,
        'direction' => LogDirection::Inbound,
        'summary' => 'Heartbeat received',
        'payload' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    get('/dashboard')
        ->assertOk()
        ->assertSee('Inactive Client')
        ->assertSee('Active Client')
        ->assertSee('bg-red-500')
        ->assertSee('bg-green-500')
        ->assertSee('No activity yet');
});
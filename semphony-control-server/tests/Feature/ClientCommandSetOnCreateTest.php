<?php

use App\Models\Client;
use App\Models\ClientType;
use App\Models\Command;
use App\Models\System;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use function Pest\Laravel\actingAs;

test('admins can create a client and optionally attach the client type command set', function () {
    Role::query()->firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);

    /** @var User $admin */
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    actingAs($admin);

    $system = System::factory()->create();

    $commandNames = [
        'beam_on_off_toggle',
        'vacuum_stndby',
        'vacuum_vent',
        'vacuum_pump',
        'rbse_push_in',
        'rbse_pull_out',
        'detector_mix_a',
        'detector_mix_a_plus_b',
        'detector_mix_a_min_b',
        'detector_mix_a_and_b',
        'detector_mix_abcd',
        'trigger_degauss',
        'acquire',
        'continual_mode',
        'single_mode',
        'stage_control_stop',
    ];

    $commands = collect($commandNames)->map(fn (string $name): Command => Command::factory()->create([
        'name' => $name,
    ]));

    $clientType = ClientType::factory()->create([
        'name' => 'TESCAN SEM',
        'slug' => 'tescan_sem',
    ]);
    $clientType->commands()->sync($commands->pluck('id')->all());

    Livewire::test(\App\Livewire\Clients\Create::class)
        ->set('systemId', $system->id)
        ->set('clientTypeId', $clientType->id)
        ->set('addCommandSet', true)
        ->set('name', 'TESCAN Client 1')
        ->set('apiKey', 'tescan-client-1-api-key')
        ->set('widthPx', 800)
        ->set('heightPx', 600)
        ->set('canScreenshot', false)
        ->set('isActive', true)
        ->call('save')
        ->assertRedirect(route('clients.index', absolute: false));

    $clientWithSet = Client::query()->where('api_key', 'tescan-client-1-api-key')->firstOrFail();

    expect($clientWithSet->client_type_id)->toBe($clientType->id);
    expect($clientWithSet->commands()->pluck('name')->all())->toEqualCanonicalizing($commandNames);

    Livewire::test(\App\Livewire\Clients\Create::class)
        ->set('systemId', $system->id)
        ->set('clientTypeId', $clientType->id)
        ->set('addCommandSet', false)
        ->set('name', 'TESCAN Client 2')
        ->set('apiKey', 'tescan-client-2-api-key')
        ->set('widthPx', 800)
        ->set('heightPx', 600)
        ->set('canScreenshot', false)
        ->set('isActive', true)
        ->call('save')
        ->assertRedirect(route('clients.index', absolute: false));

    $clientWithoutSet = Client::query()->where('api_key', 'tescan-client-2-api-key')->firstOrFail();

    expect($clientWithoutSet->client_type_id)->toBe($clientType->id);
    expect($clientWithoutSet->commands()->count())->toBe(0);
});


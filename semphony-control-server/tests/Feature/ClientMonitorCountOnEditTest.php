<?php

use App\Models\Client;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use function Pest\Laravel\actingAs;

test('admins can set monitor_count when client can screenshot', function () {
    Role::query()->firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);

    /** @var User $admin */
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    actingAs($admin);

    $client = Client::factory()->create([
        'can_screenshot' => true,
        'monitor_count' => null,
    ]);

    Livewire::test(\App\Livewire\Clients\Edit::class, ['client' => $client])
        ->set('canScreenshot', true)
        ->set('monitorCount', 2)
        ->call('save')
        ->assertRedirect(route('clients.index', absolute: false));

    $client->refresh();
    expect($client->monitor_count)->toBe(2);
});

test('monitor_count is cleared when client cannot screenshot', function () {
    Role::query()->firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);

    /** @var User $admin */
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    actingAs($admin);

    $client = Client::factory()->create([
        'can_screenshot' => true,
        'monitor_count' => 3,
    ]);

    Livewire::test(\App\Livewire\Clients\Edit::class, ['client' => $client])
        ->set('canScreenshot', false)
        ->set('monitorCount', 3)
        ->call('save')
        ->assertRedirect(route('clients.index', absolute: false));

    $client->refresh();
    expect($client->monitor_count)->toBeNull();
});


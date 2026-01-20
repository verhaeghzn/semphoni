<?php

use App\Models\Client;
use App\Models\Command;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use function Pest\Laravel\actingAs;

test('admins can edit a client and update supported commands', function () {
    Role::query()->firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);

    /** @var User $admin */
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    actingAs($admin);

    $client = Client::factory()->create();

    $commandA = Command::factory()->create(['name' => 'command_a']);
    $commandB = Command::factory()->create(['name' => 'command_b']);

    $client->commands()->sync([$commandA->id]);

    Livewire::test(\App\Livewire\Clients\Edit::class, ['client' => $client])
        ->set('supportedCommandIds', [$commandA->id, $commandB->id])
        ->call('save')
        ->assertRedirect(route('clients.index', absolute: false));

    $client->refresh();
    expect($client->commands()->pluck('name')->all())->toEqualCanonicalizing(['command_a', 'command_b']);

    Livewire::test(\App\Livewire\Clients\Edit::class, ['client' => $client])
        ->set('supportedCommandIds', [])
        ->call('save')
        ->assertRedirect(route('clients.index', absolute: false));

    $client->refresh();
    expect($client->commands()->count())->toBe(0);
});


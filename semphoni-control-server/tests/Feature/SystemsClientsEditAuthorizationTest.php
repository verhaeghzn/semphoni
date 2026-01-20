<?php

use App\Models\Client;
use App\Models\System;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('regular users cannot access system and client create/edit pages', function () {
    /** @var User $user */
    $user = User::factory()->create();
    actingAs($user);

    $system = System::factory()->create();
    $client = Client::factory()->create();

    get(route('systems.create', absolute: false))->assertForbidden();
    get(route('systems.edit', $system, absolute: false))->assertForbidden();

    get(route('clients.create', absolute: false))->assertForbidden();
    get(route('clients.edit', $client, absolute: false))->assertForbidden();
});

test('admins can access system and client create/edit pages', function () {
    Role::query()->firstOrCreate([
        'name' => 'Admin',
        'guard_name' => 'web',
    ]);

    /** @var User $admin */
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    actingAs($admin);

    $system = System::factory()->create();
    $client = Client::factory()->create();

    get(route('systems.create', absolute: false))->assertOk();
    get(route('systems.edit', $system, absolute: false))->assertOk();

    get(route('clients.create', absolute: false))->assertOk();
    get(route('clients.edit', $client, absolute: false))->assertOk();
});

test('regular users cannot render the system/client edit livewire components', function () {
    /** @var User $user */
    $user = User::factory()->create();
    actingAs($user);

    $system = System::factory()->create();
    $client = Client::factory()->create();

    Livewire::test(\App\Livewire\Systems\Edit::class, ['system' => $system])
        ->assertForbidden();

    Livewire::test(\App\Livewire\Clients\Edit::class, ['client' => $client])
        ->assertForbidden();
});


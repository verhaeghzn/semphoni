<?php

use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('regular users cannot access account management pages', function () {
    /** @var User $user */
    $user = User::factory()->create();
    actingAs($user);

    get(route('users.index', absolute: false))->assertForbidden();
    get(route('users.create', absolute: false))->assertForbidden();
    get(route('users.edit', $user, absolute: false))->assertForbidden();
});

test('admins can access account management pages', function () {
    Role::query()->firstOrCreate([
        'name' => 'Admin',
        'guard_name' => 'web',
    ]);

    /** @var User $admin */
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    actingAs($admin);

    $targetUser = User::factory()->create();

    get(route('users.index', absolute: false))->assertOk();
    get(route('users.create', absolute: false))->assertOk();
    get(route('users.edit', $targetUser, absolute: false))->assertOk();
});

test('regular users cannot render the account management livewire components', function () {
    /** @var User $user */
    $user = User::factory()->create();
    actingAs($user);

    Livewire::test(\App\Livewire\Users\Index::class)->assertForbidden();
    Livewire::test(\App\Livewire\Users\Create::class)->assertForbidden();
    Livewire::test(\App\Livewire\Users\Edit::class, ['user' => $user])->assertForbidden();
});


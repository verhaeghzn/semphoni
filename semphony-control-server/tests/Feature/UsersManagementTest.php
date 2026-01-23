<?php

use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use function Pest\Laravel\actingAs;

test('admins can create a user account', function () {
    Role::query()->firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
    Role::query()->firstOrCreate(['name' => 'User', 'guard_name' => 'web']);

    /** @var User $admin */
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    actingAs($admin);

    Livewire::test(\App\Livewire\Users\Create::class)
        ->set('name', 'Example User')
        ->set('email', 'example-user@example.com')
        ->set('role', 'User')
        ->set('emailVerified', true)
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('save')
        ->assertRedirect(route('users.index', absolute: false));

    $created = User::query()->where('email', 'example-user@example.com')->first();

    expect($created)->not->toBeNull();
    expect($created?->hasRole('User'))->toBeTrue();
    expect($created?->email_verified_at)->not->toBeNull();
});


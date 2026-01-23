<?php

use App\Models\User;

it('creates an admin user interactively', function () {
    $this->artisan('user:add-admin')
        ->expectsQuestion('Email', 'admin2@example.com')
        ->expectsQuestion('Name', 'Admin Two')
        ->expectsQuestion('Password', 'Password123!')
        ->expectsQuestion('Confirm password', 'Password123!')
        ->expectsConfirmation('Mark email as verified?', 'yes')
        ->assertSuccessful();

    $user = User::query()->where('email', 'admin2@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->hasRole('Admin'))->toBeTrue();
    expect($user->email_verified_at)->not->toBeNull();
});

it('promotes an existing user to admin', function () {
    $user = User::factory()->create([
        'email' => 'existing@example.com',
    ]);

    $this->artisan('user:add-admin --email=existing@example.com')
        ->expectsConfirmation("User {$user->name} <{$user->email}> already exists. Assign the Admin role?", 'yes')
        ->assertSuccessful();

    $user->refresh();

    expect($user->hasRole('Admin'))->toBeTrue();
});


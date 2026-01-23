<?php

use App\Enums\ActionType;
use App\Models\Client;
use App\Models\Command;
use App\Models\System;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('visiting a system control page acquires the lock', function () {
    /** @var User $user */
    $user = User::factory()->create();
    actingAs($user);

    $system = System::factory()->create();

    get(route('systems.show', $system, absolute: false))->assertOk();

    $system->refresh();

    expect($system->control_locked_by_user_id)->toBe($user->id);
    expect($system->control_locked_until)->not->toBeNull();
    expect($system->control_locked_until?->isFuture())->toBeTrue();
});

test('non-owner users cannot dispatch commands while a system is locked', function () {
    /** @var User $owner */
    $owner = User::factory()->create();

    /** @var User $otherUser */
    $otherUser = User::factory()->create();

    $system = System::factory()->create();

    $client = Client::factory()->create([
        'system_id' => $system->id,
        'is_active' => true,
    ]);

    $command = Command::factory()->create([
        'name' => 'acquire',
        'action_type' => ActionType::ButtonPress,
    ]);

    $client->commands()->syncWithoutDetaching([$command->id]);

    $permission = Permission::query()->firstOrCreate([
        'name' => $command->permissionName(),
        'guard_name' => 'web',
    ]);

    $owner->givePermissionTo($permission);
    $otherUser->givePermissionTo($permission);

    actingAs($owner);
    get(route('systems.show', $system, absolute: false))->assertOk();

    actingAs($otherUser);

    Livewire::test(\App\Livewire\Systems\Show::class, ['system' => $system])
        ->set('selectedClientId', $client->id)
        ->set('commandId', $command->id)
        ->call('dispatchSelectedCommand')
        ->assertForbidden();
});

test('a different user can take over after the lock expires', function () {
    /** @var User $owner */
    $owner = User::factory()->create();

    /** @var User $newOwner */
    $newOwner = User::factory()->create();

    $system = System::factory()->create([
        'control_locked_by_user_id' => $owner->id,
        'control_locked_until' => now()->subMinute(),
    ]);

    actingAs($newOwner);
    get(route('systems.show', $system, absolute: false))->assertOk();

    $system->refresh();

    expect($system->control_locked_by_user_id)->toBe($newOwner->id);
    expect($system->control_locked_until)->not->toBeNull();
    expect($system->control_locked_until?->isFuture())->toBeTrue();
});

test('the lock can be released by the owner', function () {
    /** @var User $owner */
    $owner = User::factory()->create();
    actingAs($owner);

    $system = System::factory()->create();

    get(route('systems.show', $system, absolute: false))->assertOk();

    Livewire::test(\App\Livewire\Systems\Show::class, ['system' => $system])
        ->call('releaseControlLock')
        ->assertRedirect(route('dashboard', absolute: false));

    $system->refresh();

    expect($system->control_locked_by_user_id)->toBeNull();
    expect($system->control_locked_until)->toBeNull();
});

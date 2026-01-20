<?php

use App\Enums\ActionType;
use App\Models\Command;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Role;

test('vacuum vent and pump are admin-only', function () {
    Command::query()->create([
        'name' => 'vacuum_vent',
        'action_type' => ActionType::ButtonPress,
        'description' => null,
    ]);

    Command::query()->create([
        'name' => 'vacuum_pump',
        'action_type' => ActionType::ButtonPress,
        'description' => null,
    ]);

    Command::query()->create([
        'name' => 'beam_on_off_toggle',
        'action_type' => ActionType::ButtonPress,
        'description' => null,
    ]);

    $this->seed(RolePermissionSeeder::class);

    $userRole = Role::query()->where('name', 'User')->firstOrFail();
    $adminRole = Role::query()->where('name', 'Admin')->firstOrFail();

    expect($adminRole->hasPermissionTo('command.execute.vacuum_vent'))->toBeTrue();
    expect($adminRole->hasPermissionTo('command.execute.vacuum_pump'))->toBeTrue();
    expect($adminRole->hasPermissionTo('command.execute.beam_on_off_toggle'))->toBeTrue();

    expect($userRole->hasPermissionTo('command.execute.vacuum_vent'))->toBeFalse();
    expect($userRole->hasPermissionTo('command.execute.vacuum_pump'))->toBeFalse();
    expect($userRole->hasPermissionTo('command.execute.beam_on_off_toggle'))->toBeTrue();
});


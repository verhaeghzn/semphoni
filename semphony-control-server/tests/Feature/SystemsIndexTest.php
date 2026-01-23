<?php

use App\Models\System;
use App\Models\User;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('systems index lists systems and shows the control button', function () {
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($user);

    System::factory()->create([
        'name' => 'System A',
    ]);

    get(route('systems.index', absolute: false))
        ->assertOk()
        ->assertSee('System A')
        ->assertSee('Control');
});


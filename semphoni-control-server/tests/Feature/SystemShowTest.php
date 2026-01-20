<?php

use App\Models\System;
use App\Models\User;
use App\Models\Client;
use App\Models\ClientLog;
use App\Models\ClientScreenshot;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('system show page renders with tabs', function () {
    /** @var User $user */
    $user = User::factory()->create();
    actingAs($user);

    $system = System::factory()->create([
        'name' => 'System A',
    ]);

    $client = Client::factory()->create([
        'system_id' => $system->id,
        'name' => 'Client A',
    ]);

    ClientScreenshot::query()->create([
        'client_id' => $client->id,
        'mime' => 'image/png',
        'base64' => 'iVBORw0KGgo=',
        'taken_at' => Carbon::create(2026, 1, 19, 12, 34, 56, 'UTC'),
    ]);

    get(route('systems.show', $system, absolute: false))
        ->assertOk()
        ->assertSee('Command Center')
        ->assertSee('Logs')
        ->assertSee('Clients')
        ->assertDontSee('Refresh response')
        ->assertDontSee('Correlation ID')
        ->assertSee('Last captured:')
        ->assertSee('datetime="2026-01-19T12:34:56', escape: false);
});

test('system show page disables send command and shows offline banner when client is offline', function () {
    $now = Carbon::create(2026, 1, 20, 12, 0, 30, 'UTC');
    Carbon::setTestNow($now);

    /** @var User $user */
    $user = User::factory()->create();
    actingAs($user);

    $system = System::factory()->create();

    $client = Client::factory()->create([
        'system_id' => $system->id,
        'name' => 'Client Offline',
        'is_active' => true,
    ]);

    $lastSeenAt = Carbon::create(2026, 1, 20, 11, 55, 0, 'UTC');

    ClientLog::factory()->create([
        'client_id' => $client->id,
        'created_at' => $lastSeenAt,
        'updated_at' => $lastSeenAt,
    ]);

    get(route('systems.show', $system, absolute: false))
        ->assertOk()
        ->assertSee('Client is offline since')
        ->assertSee('datetime="2026-01-20T11:55:00', escape: false)
        ->assertSee('dispatchSelectedCommand', escape: false)
        ->assertSee('disabled', escape: false);

    Carbon::setTestNow();
});

test('system show page does not show offline banner when client is online', function () {
    $now = Carbon::create(2026, 1, 20, 12, 0, 30, 'UTC');
    Carbon::setTestNow($now);

    /** @var User $user */
    $user = User::factory()->create();
    actingAs($user);

    $system = System::factory()->create();

    $client = Client::factory()->create([
        'system_id' => $system->id,
        'name' => 'Client Online',
        'is_active' => true,
    ]);

    $lastSeenAt = Carbon::create(2026, 1, 20, 12, 0, 25, 'UTC');

    ClientLog::factory()->create([
        'client_id' => $client->id,
        'created_at' => $lastSeenAt,
        'updated_at' => $lastSeenAt,
    ]);

    get(route('systems.show', $system, absolute: false))
        ->assertOk()
        ->assertDontSee('Client is offline since');

    Carbon::setTestNow();
});

test('visual feed fullscreen mode can be toggled and exits when leaving command center', function () {
    /** @var User $user */
    $user = User::factory()->create();
    actingAs($user);

    $system = System::factory()->create();

    Client::factory()->create([
        'system_id' => $system->id,
        'name' => 'Client A',
    ]);

    Livewire::test(\App\Livewire\Systems\Show::class, ['system' => $system])
        ->assertSet('visualFeedFullscreen', false)
        ->call('toggleVisualFeedFullscreen')
        ->assertSet('visualFeedFullscreen', true)
        ->call('selectTab', 'logs')
        ->assertSet('visualFeedFullscreen', false);
});

test('system show loads saved screenshot as webp data url when available', function () {
    /** @var User $user */
    $user = User::factory()->create();
    actingAs($user);

    $system = System::factory()->create();

    $client = Client::factory()->create([
        'system_id' => $system->id,
        'name' => 'Client A',
    ]);

    ClientScreenshot::query()->create([
        'client_id' => $client->id,
        'mime' => 'image/webp',
        'base64' => 'UklGRg==',
        'taken_at' => Carbon::create(2026, 1, 19, 12, 34, 56, 'UTC'),
    ]);

    Livewire::test(\App\Livewire\Systems\Show::class, ['system' => $system])
        ->assertSet('screenshotDataUrl', 'data:image/webp;base64,UklGRg==');
});


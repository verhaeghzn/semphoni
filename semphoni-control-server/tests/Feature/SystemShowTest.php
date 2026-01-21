<?php

use App\Models\System;
use App\Models\User;
use App\Models\Client;
use App\Models\ClientLog;
use App\Models\ClientScreenshot;
use App\Events\ClientCommandDispatched;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
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
        'can_screenshot' => true,
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

test('client visual feed loads saved screenshot as webp data url when available', function () {
    /** @var User $user */
    $user = User::factory()->create();
    actingAs($user);

    $system = System::factory()->create();

    $client = Client::factory()->create([
        'system_id' => $system->id,
        'name' => 'Client A',
        'can_screenshot' => true,
    ]);

    ClientScreenshot::query()->create([
        'client_id' => $client->id,
        'mime' => 'image/webp',
        'base64' => 'UklGRg==',
        'taken_at' => Carbon::create(2026, 1, 19, 12, 34, 56, 'UTC'),
    ]);

    Livewire::test(\App\Livewire\Systems\ClientVisualFeed::class, [
        'systemId' => $system->id,
        'clientId' => $client->id,
        'canControl' => true,
    ])
        ->assertSet('screenshotDataUrl', 'data:image/webp;base64,UklGRg==');
});

test('visual feed screenshot request includes configured monitor number', function () {
    Event::fake([ClientCommandDispatched::class]);

    /** @var User $user */
    $user = User::factory()->create();
    actingAs($user);

    $system = System::factory()->create();

    $now = Carbon::create(2026, 1, 20, 12, 0, 0, 'UTC');
    Carbon::setTestNow($now);

    $client = Client::factory()->create([
        'system_id' => $system->id,
        'can_screenshot' => true,
        'is_active' => true,
    ]);

    ClientLog::factory()->create([
        'client_id' => $client->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    Livewire::test(\App\Livewire\Systems\ClientVisualFeed::class, [
        'systemId' => $system->id,
        'clientId' => $client->id,
        'canControl' => true,
    ])
        ->set('visualFeedMonitorNr', 3)
        ->set('visualFeedEnabled', true);

    Event::assertDispatched(ClientCommandDispatched::class, function (ClientCommandDispatched $event): bool {
        expect($event->commandName)->toBe('get_screenshot');
        expect($event->payload)->toMatchArray([
            'monitor_nr' => 3,
        ]);

        return true;
    });

    Carbon::setTestNow();
});

test('visual feed panel shows offline badge and disables live toggle when client is offline', function () {
    /** @var User $user */
    $user = User::factory()->create();
    actingAs($user);

    $system = System::factory()->create();

    $client = Client::factory()->create([
        'system_id' => $system->id,
        'can_screenshot' => true,
        'is_active' => true,
    ]);

    Livewire::test(\App\Livewire\Systems\ClientVisualFeed::class, [
        'systemId' => $system->id,
        'clientId' => $client->id,
        'canControl' => true,
    ])
        ->assertSee('OFFLINE')
        ->assertSee('disabled', escape: false);
});

test('visual feed clamps monitor selection to client monitor_count when configured', function () {
    Event::fake([ClientCommandDispatched::class]);

    /** @var User $user */
    $user = User::factory()->create();
    actingAs($user);

    $system = System::factory()->create();

    $now = Carbon::create(2026, 1, 20, 12, 0, 0, 'UTC');
    Carbon::setTestNow($now);

    $client = Client::factory()->create([
        'system_id' => $system->id,
        'can_screenshot' => true,
        'monitor_count' => 1,
        'is_active' => true,
    ]);

    ClientLog::factory()->create([
        'client_id' => $client->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    Livewire::test(\App\Livewire\Systems\ClientVisualFeed::class, [
        'systemId' => $system->id,
        'clientId' => $client->id,
        'canControl' => true,
    ])
        ->set('visualFeedMonitorNr', 3)
        ->set('visualFeedEnabled', true)
        ->assertSet('visualFeedMonitorNr', 1);

    Event::assertDispatched(ClientCommandDispatched::class, function (ClientCommandDispatched $event): bool {
        expect($event->commandName)->toBe('get_screenshot');
        expect($event->payload)->toMatchArray([
            'monitor_nr' => 1,
        ]);

        return true;
    });

    Carbon::setTestNow();
});

test('visual feed persists and reloads latest interval and monitor settings', function () {
    /** @var User $user */
    $user = User::factory()->create();
    actingAs($user);

    $system = System::factory()->create();

    $client = Client::factory()->create([
        'system_id' => $system->id,
        'can_screenshot' => true,
        'monitor_count' => 3,
    ]);

    $sessionKey = 'visual_feed.settings.user_'.$user->id.'.client_'.$client->id;

    Livewire::test(\App\Livewire\Systems\ClientVisualFeed::class, [
        'systemId' => $system->id,
        'clientId' => $client->id,
        'canControl' => true,
    ])
        ->set('visualFeedIntervalSeconds', 12)
        ->set('visualFeedMonitorNr', 2);

    expect(session()->get($sessionKey))->toMatchArray([
        'interval_seconds' => 12,
        'monitor_nr' => 2,
    ]);

    Livewire::test(\App\Livewire\Systems\ClientVisualFeed::class, [
        'systemId' => $system->id,
        'clientId' => $client->id,
        'canControl' => true,
    ])
        ->assertSet('visualFeedIntervalSeconds', 12)
        ->assertSet('visualFeedMonitorNr', 2);
});


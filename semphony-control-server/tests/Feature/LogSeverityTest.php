<?php

use App\Enums\LogDirection;
use App\Enums\LogSeverity;
use App\Models\Client;
use App\Models\ClientLog;
use App\Models\Command;
use App\Models\System;
use App\Services\ClientCommandService;
use Laravel\Reverb\Application as ReverbApplication;
use Laravel\Reverb\Contracts\Connection as ReverbConnection;
use Laravel\Reverb\Contracts\WebSocketConnection;
use Laravel\Reverb\Events\MessageReceived;
use Ratchet\RFC6455\Messaging\Frame;

it('creates logs with info severity by default', function () {
    $client = Client::factory()->create([
        'is_active' => true,
    ]);

    $log = ClientLog::factory()->create([
        'client_id' => $client->id,
        'system_id' => $client->system_id,
    ]);

    expect($log->severity)->toBeInstanceOf(LogSeverity::class);
    expect($log->severity->value)->toBeIn(['info', 'error', 'critical']);
});

it('creates heartbeat logs with info severity', function () {
    $heartbeat = Command::factory()->create([
        'name' => 'heartbeat',
    ]);

    $client = Client::factory()->create([
        'is_active' => true,
    ]);

    $connection = new class(connection: new class implements WebSocketConnection
    {
        public function id(): int|string
        {
            return 1;
        }

        public function send(mixed $message): void
        {
            //
        }

        public function close(mixed $message = null): void
        {
            //
        }
    },
        application: new ReverbApplication(
            id: 'app',
            key: 'key',
            secret: 'secret',
            pingInterval: 60,
            activityTimeout: 30,
            allowedOrigins: ['*'],
            maxMessageSize: 10_000,
        ),
        origin: null,
    ) extends ReverbConnection
    {

        public function identifier(): string
        {
            return '1';
        }

        public function id(): string
        {
            return '1234.5678';
        }

        public function send(string $message): void
        {
            //
        }

        public function control(string $type = Frame::OP_PING): void
        {
            //
        }

        public function terminate(): void
        {
            //
        }
    };

    $message = json_encode([
        'event' => 'client-heartbeat',
        'channel' => 'presence-client.'.$client->id,
        'data' => json_encode(['ok' => true]),
    ]);

    event(new MessageReceived($connection, $message));

    $log = ClientLog::query()
        ->where('client_id', $client->id)
        ->where('summary', 'Heartbeat')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->severity)->toBe(LogSeverity::Info);
});

it('creates command result logs with error severity when command fails', function () {
    $client = Client::factory()->create([
        'is_active' => true,
    ]);

    $connection = new class(connection: new class implements WebSocketConnection
    {
        public function id(): int|string
        {
            return 1;
        }

        public function send(mixed $message): void
        {
            //
        }

        public function close(mixed $message = null): void
        {
            //
        }
    },
        application: new ReverbApplication(
            id: 'app',
            key: 'key',
            secret: 'secret',
            pingInterval: 60,
            activityTimeout: 30,
            allowedOrigins: ['*'],
            maxMessageSize: 10_000,
        ),
        origin: null,
    ) extends ReverbConnection
    {

        public function identifier(): string
        {
            return '1';
        }

        public function id(): string
        {
            return '1234.5678';
        }

        public function send(string $message): void
        {
            //
        }

        public function control(string $type = Frame::OP_PING): void
        {
            //
        }

        public function terminate(): void
        {
            //
        }
    };

    $message = json_encode([
        'event' => 'client-command-result',
        'channel' => 'presence-client.'.$client->id,
        'data' => json_encode([
            'ok' => false,
            'correlation_id' => 'test-correlation-id',
        ]),
    ]);

    event(new MessageReceived($connection, $message));

    $log = ClientLog::query()
        ->where('client_id', $client->id)
        ->where('summary', 'like', 'Command result%')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->severity)->toBe(LogSeverity::Error);
});

it('creates command result logs with info severity when command succeeds', function () {
    $client = Client::factory()->create([
        'is_active' => true,
    ]);

    $connection = new class(connection: new class implements WebSocketConnection
    {
        public function id(): int|string
        {
            return 1;
        }

        public function send(mixed $message): void
        {
            //
        }

        public function close(mixed $message = null): void
        {
            //
        }
    },
        application: new ReverbApplication(
            id: 'app',
            key: 'key',
            secret: 'secret',
            pingInterval: 60,
            activityTimeout: 30,
            allowedOrigins: ['*'],
            maxMessageSize: 10_000,
        ),
        origin: null,
    ) extends ReverbConnection
    {

        public function identifier(): string
        {
            return '1';
        }

        public function id(): string
        {
            return '1234.5678';
        }

        public function send(string $message): void
        {
            //
        }

        public function control(string $type = Frame::OP_PING): void
        {
            //
        }

        public function terminate(): void
        {
            //
        }
    };

    $message = json_encode([
        'event' => 'client-command-result',
        'channel' => 'presence-client.'.$client->id,
        'data' => json_encode([
            'ok' => true,
            'correlation_id' => 'test-correlation-id',
        ]),
    ]);

    event(new MessageReceived($connection, $message));

    $log = ClientLog::query()
        ->where('client_id', $client->id)
        ->where('summary', 'like', 'Command result%')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->severity)->toBe(LogSeverity::Info);
});

it('creates outbound command logs with info severity', function () {
    $system = System::factory()->create();
    $client = Client::factory()->for($system)->create([
        'is_active' => true,
    ]);

    $command = Command::factory()->create();
    $client->commands()->attach($command);

    $service = app(ClientCommandService::class);
    $service->dispatchToClient($client, $command);

    $log = ClientLog::query()
        ->where('client_id', $client->id)
        ->where('direction', LogDirection::Outbound)
        ->where('command_id', $command->id)
        ->first();

    expect($log)->not->toBeNull();
    expect($log->severity)->toBe(LogSeverity::Info);
});

it('creates pusher activity logs with info severity', function () {
    $client = Client::factory()->create([
        'is_active' => true,
    ]);

    $connection = new class(connection: new class implements WebSocketConnection
    {
        public function id(): int|string
        {
            return 1;
        }

        public function send(mixed $message): void
        {
            //
        }

        public function close(mixed $message = null): void
        {
            //
        }
    },
        application: new ReverbApplication(
            id: 'app',
            key: 'key',
            secret: 'secret',
            pingInterval: 60,
            activityTimeout: 30,
            allowedOrigins: ['*'],
            maxMessageSize: 10_000,
        ),
        origin: null,
    ) extends ReverbConnection
    {

        public function identifier(): string
        {
            return '1';
        }

        public function id(): string
        {
            return '1234.5678';
        }

        public function send(string $message): void
        {
            //
        }

        public function control(string $type = Frame::OP_PING): void
        {
            //
        }

        public function terminate(): void
        {
            //
        }
    };

    $message = json_encode([
        'event' => 'pusher:subscribe',
        'channel' => 'presence-client.'.$client->id,
        'data' => json_encode([]),
    ]);

    event(new MessageReceived($connection, $message));

    $log = ClientLog::query()
        ->where('client_id', $client->id)
        ->where('summary', 'Subscribed')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->severity)->toBe(LogSeverity::Info);
});

it('filters logs by severity in the logs viewer', function () {
    $user = \App\Models\User::factory()->create();
    $this->actingAs($user);

    $system = System::factory()->create();
    $client = Client::factory()->for($system)->create();

    ClientLog::factory()->create([
        'client_id' => $client->id,
        'system_id' => $system->id,
        'severity' => LogSeverity::Info,
        'summary' => 'Unique info summary for filter test',
        'direction' => LogDirection::Inbound,
    ]);

    ClientLog::factory()->create([
        'client_id' => $client->id,
        'system_id' => $system->id,
        'severity' => LogSeverity::Error,
        'summary' => 'Unique error summary for filter test',
        'direction' => LogDirection::Inbound,
    ]);

    $component = \Livewire\Livewire::test(\App\Livewire\Logs\Index::class);

    $component->assertSee('Unique info summary for filter test')
        ->assertSee('Unique error summary for filter test');

    $component->set('severityFilter', 'error')
        ->assertSee('Unique error summary for filter test')
        ->assertDontSee('Unique info summary for filter test');

    $component->set('severityFilter', 'info')
        ->assertSee('Unique info summary for filter test')
        ->assertDontSee('Unique error summary for filter test');

    $component->set('severityFilter', '')
        ->assertSee('Unique info summary for filter test')
        ->assertSee('Unique error summary for filter test');
});

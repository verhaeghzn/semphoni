<?php

use App\Enums\ActionType;
use App\Models\Client;
use App\Models\ClientLog;
use App\Models\Command;
use Laravel\Reverb\Application as ReverbApplication;
use Laravel\Reverb\Contracts\Connection as ReverbConnection;
use Laravel\Reverb\Contracts\WebSocketConnection;
use Laravel\Reverb\Events\MessageReceived;
use Ratchet\RFC6455\Messaging\Frame;

it('logs client heartbeats received via reverb', function () {
    $heartbeat = Command::factory()->create([
        'name' => 'heartbeat',
        'action_type' => ActionType::Heartbeat,
    ]);

    $client = Client::factory()->create([
        'is_active' => true,
    ]);

    $connection = new class(
        connection: new class implements WebSocketConnection
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

    expect($message)->toBeString();

    foreach (range(1, 12) as $i) {
        event(new MessageReceived($connection, $message));
    }

    $heartbeatLogs = ClientLog::query()
        ->where('client_id', $client->id)
        ->where('summary', 'Heartbeat')
        ->where('command_id', $heartbeat->id)
        ->orderByDesc('id')
        ->get();

    expect($heartbeatLogs)->toHaveCount(10);

    $latestLog = $heartbeatLogs->first();
    expect($latestLog)->not->toBeNull();
    expect($latestLog->summary)->toBe('Heartbeat');
    expect($latestLog->command_id)->toBe($heartbeat->id);
    expect($latestLog->direction->value)->toBe('inbound');
});


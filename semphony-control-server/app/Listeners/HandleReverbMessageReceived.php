<?php

namespace App\Listeners;

use App\Enums\LogDirection;
use App\Models\Client;
use App\Models\ClientLog;
use App\Models\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Reverb\Events\MessageReceived;
use Throwable;

class HandleReverbMessageReceived
{
    public function handle(MessageReceived $event): void
    {
        $payload = $this->decodeJson($event->message);

        if (! is_array($payload)) {
            return;
        }

        $eventName = $payload['event'] ?? null;

        if (! is_string($eventName) || $eventName === '') {
            return;
        }

        if (isset($payload['data']) && is_string($payload['data']) && Str::isJson($payload['data'])) {
            $payload['data'] = $this->decodeJson($payload['data']);
        }

        $socketId = $event->connection->id();
        $channel = is_string($payload['channel'] ?? null) ? $payload['channel'] : null;

        $clientId = $this->extractClientIdFromChannel($channel);

        if ($clientId !== null) {
            Cache::put($this->socketClientCacheKey($socketId), $clientId, now()->addDay());
        } else {
            $cachedClientId = Cache::get($this->socketClientCacheKey($socketId));

            if (is_int($cachedClientId)) {
                $clientId = $cachedClientId;
            } elseif (is_string($cachedClientId) && ctype_digit($cachedClientId)) {
                $clientId = (int) $cachedClientId;
            }
        }

        if ($clientId === null) {
            return;
        }

        $client = Client::query()->find($clientId);

        if (! $client instanceof Client || $client->is_active === false) {
            return;
        }

        $sanitizedPayload = $this->sanitizeForStorage($payload, $socketId);

        match ($eventName) {
            'client-heartbeat' => $this->storeHeartbeat($client, $sanitizedPayload),
            'client-command-result' => $this->storeCommandResult($client, $sanitizedPayload),
            'pusher:subscribe' => $this->storePusherActivityLog($client, 'Subscribed', $sanitizedPayload),
            'pusher:unsubscribe' => $this->storePusherActivityLog($client, 'Unsubscribed', $sanitizedPayload),
            'pusher:ping' => $this->storePusherActivityLog($client, 'Ping', $sanitizedPayload),
            'pusher:pong' => $this->storePusherActivityLog($client, 'Pong', $sanitizedPayload),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJson(string $json): ?array
    {
        try {
            $decoded = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function socketClientCacheKey(string $socketId): string
    {
        return 'reverb:socket-client:'.$socketId;
    }

    private function extractClientIdFromChannel(?string $channel): ?int
    {
        if (! is_string($channel)) {
            return null;
        }

        if (! preg_match('/^presence-client\.(\d+)$/', $channel, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanitizeForStorage(array $payload, string $socketId): array
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        unset($data['auth']);

        return [
            'event' => $payload['event'] ?? null,
            'channel' => $payload['channel'] ?? null,
            'socket_id' => $socketId,
            'data' => $data,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storeHeartbeat(Client $client, array $payload): void
    {
        $heartbeatCommandId = Cache::remember('commands:heartbeat:id', now()->addHour(), function () {
            return Command::query()
                ->where('name', 'heartbeat')
                ->value('id');
        });

        ClientLog::query()->create([
            'client_id' => $client->id,
            'system_id' => $client->system_id,
            'direction' => LogDirection::Inbound,
            'command_id' => is_int($heartbeatCommandId) ? $heartbeatCommandId : null,
            'summary' => 'Heartbeat',
            'payload' => $payload,
        ]);

        $this->pruneHeartbeats($client, is_int($heartbeatCommandId) ? $heartbeatCommandId : null);
    }

    private function pruneHeartbeats(Client $client, ?int $heartbeatCommandId, int $keep = 10): void
    {
        $baseQuery = ClientLog::query()
            ->where('client_id', $client->id)
            ->where('direction', LogDirection::Inbound)
            ->where('summary', 'Heartbeat')
            ->when($heartbeatCommandId !== null, function ($query) use ($heartbeatCommandId): void {
                $query->where('command_id', $heartbeatCommandId);
            });

        $idsToKeep = (clone $baseQuery)
            ->latest('id')
            ->limit($keep)
            ->pluck('id');

        if ($idsToKeep->isEmpty()) {
            return;
        }

        (clone $baseQuery)
            ->whereNotIn('id', $idsToKeep)
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storeCommandResult(Client $client, array $payload): void
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        $commandId = null;

        if (is_int($data['command_id'] ?? null)) {
            $commandId = $data['command_id'];
        } elseif (is_string($data['command_id'] ?? null) && ctype_digit($data['command_id'])) {
            $commandId = (int) $data['command_id'];
        } elseif (is_string($data['command_name'] ?? null) && $data['command_name'] !== '') {
            if ($data['command_name'] === 'clickButton') {
                $buttonName = $this->extractButtonNameFromResult($data);

                if ($buttonName !== null) {
                    $commandId = Command::query()
                        ->where('name', $buttonName)
                        ->value('id');
                }
            } else {
                $commandId = Command::query()
                    ->where('name', $data['command_name'])
                    ->value('id');
            }
        }

        $correlationId = is_string($data['correlation_id'] ?? null) ? $data['correlation_id'] : null;

        ClientLog::query()->create([
            'client_id' => $client->id,
            'system_id' => $client->system_id,
            'direction' => LogDirection::Inbound,
            'command_id' => is_int($commandId) ? $commandId : null,
            'summary' => $correlationId ? 'Command result ('.$correlationId.')' : 'Command result',
            'payload' => $payload,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function extractButtonNameFromResult(array $data): ?string
    {
        $payload = is_array($data['payload'] ?? null) ? $data['payload'] : null;

        if (! is_array($payload)) {
            return null;
        }

        $buttonName = $payload['button_name'] ?? null;

        if (! is_string($buttonName) || $buttonName === '') {
            return null;
        }

        return $buttonName;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storePusherActivityLog(Client $client, string $summary, array $payload): void
    {
        ClientLog::query()->create([
            'client_id' => $client->id,
            'system_id' => $client->system_id,
            'direction' => LogDirection::Inbound,
            'command_id' => null,
            'summary' => $summary,
            'payload' => $payload,
        ]);
    }
}

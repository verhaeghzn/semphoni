<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClientCommandDispatched implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $clientId,
        public string $correlationId,
        public string $commandName,
        public array $payload = [],
    ) {
        //
    }

    public function broadcastOn(): Channel
    {
        return new Channel('presence-client.'.$this->clientId);
    }

    public function broadcastAs(): string
    {
        return 'server-command';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'client_id' => $this->clientId,
            'correlation_id' => $this->correlationId,
            'command_name' => $this->commandName,
            'payload' => $this->payload,
        ];
    }
}


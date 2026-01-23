<?php

namespace App\Services;

use App\Enums\ActionType;
use App\Enums\CommandType;
use App\Enums\LogDirection;
use App\Events\ClientCommandDispatched;
use App\Models\Client;
use App\Models\ClientLog;
use App\Models\Command;
use Illuminate\Support\Str;

class ClientCommandService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function dispatchToClient(Client $client, Command $command, array $payload = []): string
    {
        if ($client->is_active === false) {
            abort(403);
        }

        $isAllowed = $command->name === 'get_screenshot' && $client->can_screenshot === true
            ? true
            : $client->commands()
                ->whereKey($command->id)
                ->exists();

        if (! $isAllowed) {
            abort(403);
        }

        $correlationId = (string) Str::uuid();

        [$outboundCommandName, $outboundPayload] = $this->buildOutboundMessage($command, $payload);

        event(new ClientCommandDispatched(
            clientId: $client->id,
            correlationId: $correlationId,
            commandName: $outboundCommandName,
            payload: $outboundPayload,
        ));

        ClientLog::query()->create([
            'client_id' => $client->id,
            'system_id' => $client->system_id,
            'direction' => LogDirection::Outbound,
            'command_id' => $command->id,
            'summary' => 'Executed '.$command->name,
            'payload' => [
                'event' => 'server-command',
                'channel' => 'presence-client.'.$client->id,
                'data' => [
                    'client_id' => $client->id,
                    'correlation_id' => $correlationId,
                    'command_id' => $command->id,
                    'command_name' => $outboundCommandName,
                    'payload' => $outboundPayload,
                ],
            ],
        ]);

        return $correlationId;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildOutboundMessage(Command $command, array $payload): array
    {
        if ($command->name === 'get_screenshot') {
            return ['get_screenshot', $payload];
        }

        if ($command->action_type === ActionType::ButtonPress) {
            $commandType = $payload['command_type'] ?? CommandType::ClickButton;

            if (is_string($commandType)) {
                $commandType = CommandType::tryFrom($commandType) ?? CommandType::ClickButton;
            }

            if (! $commandType instanceof CommandType) {
                $commandType = CommandType::ClickButton;
            }

            $filteredPayload = $payload;
            unset($filteredPayload['command_type']);

            return [
                $commandType->value,
                [
                    ...$filteredPayload,
                    'button_name' => $command->name,
                ],
            ];
        }

        return [$command->name, $payload];
    }
}

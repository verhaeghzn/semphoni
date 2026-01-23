<?php

use App\Enums\ActionType;
use App\Events\ClientCommandDispatched;
use App\Models\Client;
use App\Models\Command;
use App\Services\ClientCommandService;
use Illuminate\Support\Facades\Event;

test('button press commands are dispatched as clickButton with button_name', function () {
    Event::fake([ClientCommandDispatched::class]);

    $client = Client::factory()->create([
        'is_active' => true,
    ]);

    $command = Command::factory()->create([
        'name' => 'acquire',
        'action_type' => ActionType::ButtonPress,
    ]);

    $client->commands()->syncWithoutDetaching([$command->id]);

    $service = app(ClientCommandService::class);

    $correlationId = $service->dispatchToClient($client, $command, [
        'foo' => 'bar',
    ]);

    expect($correlationId)->toBeString();

    Event::assertDispatched(ClientCommandDispatched::class, function (ClientCommandDispatched $event) use ($client, $correlationId): bool {
        expect($event->clientId)->toBe($client->id);
        expect($event->correlationId)->toBe($correlationId);
        expect($event->commandName)->toBe('clickButton');
        expect($event->payload)->toMatchArray([
            'foo' => 'bar',
            'button_name' => 'acquire',
        ]);

        return true;
    });
});


<?php

use App\Models\Client;
use function Pest\Laravel\postJson;

it('signs presence channel auth for a client api key', function () {
    $client = Client::factory()->create([
        'api_key' => 'test-client-api-key',
        'is_active' => true,
    ]);

    $response = postJson(route('client.broadcasting.auth', absolute: false), [
            'socket_id' => '1234.5678',
            'channel_name' => 'presence-client.'.$client->id,
        ], [
            'X-Client-Key' => 'test-client-api-key',
        ]);

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'auth',
        'channel_data',
    ]);
});

it('rejects auth for other client channels', function () {
    $client = Client::factory()->create([
        'api_key' => 'test-client-api-key',
        'is_active' => true,
    ]);

    $response = postJson(route('client.broadcasting.auth', absolute: false), [
            'socket_id' => '1234.5678',
            'channel_name' => 'presence-client.'.($client->id + 1),
        ], [
            'X-Client-Key' => 'test-client-api-key',
        ]);

    $response->assertForbidden();
});

it('rejects auth when client is not active', function () {
    $client = Client::factory()->create([
        'api_key' => 'test-client-api-key',
        'is_active' => false,
    ]);

    $response = postJson(route('client.broadcasting.auth', absolute: false), [
            'socket_id' => '1234.5678',
            'channel_name' => 'presence-client.'.$client->id,
        ], [
            'X-Client-Key' => 'test-client-api-key',
        ]);

    $response->assertForbidden();
});


<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientReverbAuthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'socket_id' => ['required', 'string'],
            'channel_name' => ['required', 'string'],
        ]);

        $apiKey = $request->header('X-Client-Key');

        if (! is_string($apiKey) || $apiKey === '') {
            abort(403);
        }

        $client = Client::query()
            ->where('api_key', $apiKey)
            ->first();

        if (! $client instanceof Client || $client->is_active === false) {
            abort(403);
        }

        $channelName = $validated['channel_name'];

        if ($channelName !== 'presence-client.'.$client->id) {
            abort(403);
        }

        $appKey = (string) config('broadcasting.connections.reverb.key');
        $appSecret = (string) config('broadcasting.connections.reverb.secret');

        if ($appKey === '' || $appSecret === '') {
            abort(500);
        }

        $channelData = json_encode([
            'user_id' => (string) $client->id,
            'user_info' => [
                'client_id' => $client->id,
                'name' => $client->name,
                'system_id' => $client->system_id,
            ],
        ], JSON_UNESCAPED_SLASHES);

        if (! is_string($channelData)) {
            abort(500);
        }

        $signature = hash_hmac(
            'sha256',
            $validated['socket_id'].':'.$channelName.':'.$channelData,
            $appSecret
        );

        return response()->json([
            'auth' => $appKey.':'.$signature,
            'channel_data' => $channelData,
        ]);
    }
}


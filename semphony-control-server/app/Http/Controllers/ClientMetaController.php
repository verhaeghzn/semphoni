<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientMetaController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
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

        $pyClientVersion = (string) config('semphony.py_client_version', '');

        if ($pyClientVersion === '') {
            abort(500, 'PY_CLIENT_VERSION is not configured on the server');
        }

        return response()->json([
            'py_client_version' => $pyClientVersion,
        ]);
    }
}


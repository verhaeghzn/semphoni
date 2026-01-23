<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientScreenshot;
use App\Models\System;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientLatestScreenshotController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, System $system, Client $client, int $monitorNr): StreamedResponse
    {
        abort_unless($client->system_id === $system->id, 404);

        $screenshot = ClientScreenshot::query()
            ->where('client_id', $client->id)
            ->where('monitor_nr', $monitorNr)
            ->first();

        if (! $screenshot instanceof ClientScreenshot) {
            abort(404);
        }

        $disk = is_string($screenshot->storage_disk) && $screenshot->storage_disk !== ''
            ? $screenshot->storage_disk
            : 'local';

        $path = is_string($screenshot->storage_path) ? $screenshot->storage_path : '';

        if ($path === '' || ! Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        $mime = is_string($screenshot->mime) && $screenshot->mime !== ''
            ? $screenshot->mime
            : 'image/jpeg';

        /** @var FilesystemAdapter $storage */
        $storage = Storage::disk($disk);

        $response = $storage->response($path, headers: [
            'Content-Type' => $mime,
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Content-Disposition' => 'inline; filename="latest.jpg"',
        ]);

        return $response;
    }
}

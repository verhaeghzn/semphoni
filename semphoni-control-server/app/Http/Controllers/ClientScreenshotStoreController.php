<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientScreenshotRequest;
use App\Models\ClientScreenshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ClientScreenshotStoreController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(StoreClientScreenshotRequest $request): JsonResponse
    {
        $client = $request->clientModel();
        $validated = $request->validated();

        $monitorNr = (int) $validated['monitor_nr'];
        $maxMonitor = is_int($client->monitor_count) && $client->monitor_count > 0
            ? $client->monitor_count
            : 10;

        if ($monitorNr > $maxMonitor) {
            abort(422, 'monitor_nr exceeds client monitor_count');
        }

        /** @var UploadedFile $image */
        $image = $request->file('image');

        $disk = 'local';
        $directory = 'client-screenshots/'.$client->id.'/monitor-'.$monitorNr;

        $storagePath = Storage::disk($disk)->putFileAs(
            $directory,
            $image,
            'latest.jpg'
        );

        $mime = $image->getMimeType() ?: 'image/jpeg';
        $bytes = $image->getSize() ?: null;
        $sha256 = is_string($image->getRealPath()) ? hash_file('sha256', $image->getRealPath()) : null;

        $takenAt = now();

        $screenshot = ClientScreenshot::query()->updateOrCreate(
            [
                'client_id' => $client->id,
                'monitor_nr' => $monitorNr,
            ],
            [
                'mime' => $mime,
                'storage_disk' => $disk,
                'storage_path' => $storagePath,
                'bytes' => $bytes,
                'sha256' => $sha256,
                'taken_at' => $takenAt,
            ]
        );

        return response()->json([
            'id' => $screenshot->id,
            'client_id' => $client->id,
            'monitor_nr' => $monitorNr,
            'mime' => $mime,
            'bytes' => $bytes,
            'sha256' => $sha256,
            'taken_at' => $takenAt->toIso8601String(),
        ]);
    }
}

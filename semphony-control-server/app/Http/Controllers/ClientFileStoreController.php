<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientFileRequest;
use App\Jobs\UploadFileToStorageJob;
use App\Models\ClientFile;
use App\Models\StorageConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ClientFileStoreController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(StoreClientFileRequest $request): JsonResponse
    {
        $client = $request->clientModel();
        $validated = $request->validated();

        /** @var UploadedFile $uploadedFile */
        $uploadedFile = $request->file('file');

        $originalFilename = $validated['filename'] ?? $uploadedFile->getClientOriginalName();
        $mime = $uploadedFile->getMimeType();
        $bytes = $uploadedFile->getSize();
        $sha256 = is_string($uploadedFile->getRealPath()) ? hash_file('sha256', $uploadedFile->getRealPath()) : null;

        $storageConfigurationId = isset($validated['storage_configuration_id']) && is_int($validated['storage_configuration_id'])
            ? $validated['storage_configuration_id']
            : null;

        if ($storageConfigurationId !== null) {
            // Store temporarily and queue upload job
            $tempDisk = 'local';
            $tempDirectory = 'client-uploads-temp/'.$client->id;
            $tempPath = Storage::disk($tempDisk)->putFileAs(
                $tempDirectory,
                $uploadedFile,
                $originalFilename
            );

            $storageConfiguration = StorageConfiguration::query()->find($storageConfigurationId);

            if (! $storageConfiguration instanceof StorageConfiguration || $storageConfiguration->is_active === false) {
                Storage::disk($tempDisk)->delete($tempPath);
                abort(422, 'Invalid or inactive storage configuration');
            }

            $storageType = match ($storageConfiguration->type) {
                'sftp' => 'sftp',
                's3' => 's3',
                default => 'sfs',
            };

            // Create ClientFile record with temp path - job will update it
            $clientFile = ClientFile::query()->create([
                'client_id' => $client->id,
                'original_filename' => $originalFilename,
                'storage_type' => $storageType,
                'storage_configuration_id' => $storageConfigurationId,
                'storage_path' => $tempPath, // Temporary path, will be updated by job
                'mime' => $mime,
                'bytes' => $bytes,
                'sha256' => $sha256,
                'uploaded_at' => now(),
            ]);

            // Queue job to upload to external storage
            UploadFileToStorageJob::dispatch($clientFile, $tempPath, $originalFilename);

            return response()->json([
                'id' => $clientFile->id,
                'client_id' => $client->id,
                'original_filename' => $originalFilename,
                'storage_type' => $storageType,
                'mime' => $mime,
                'bytes' => $bytes,
                'sha256' => $sha256,
                'uploaded_at' => $clientFile->uploaded_at->toIso8601String(),
                'status' => 'queued',
            ]);
        }

        // Store in default SFS (Semphony filesystem)
        $disk = 'local';
        $directory = 'client-uploads/'.$client->id;
        $storagePath = Storage::disk($disk)->putFileAs(
            $directory,
            $uploadedFile,
            $originalFilename
        );

        $clientFile = ClientFile::query()->create([
            'client_id' => $client->id,
            'original_filename' => $originalFilename,
            'storage_type' => 'sfs',
            'storage_configuration_id' => null,
            'storage_path' => $storagePath,
            'mime' => $mime,
            'bytes' => $bytes,
            'sha256' => $sha256,
            'uploaded_at' => now(),
        ]);

        return response()->json([
            'id' => $clientFile->id,
            'client_id' => $client->id,
            'original_filename' => $originalFilename,
            'storage_type' => 'sfs',
            'mime' => $mime,
            'bytes' => $bytes,
            'sha256' => $sha256,
            'uploaded_at' => $clientFile->uploaded_at->toIso8601String(),
            'status' => 'stored',
        ]);
    }
}

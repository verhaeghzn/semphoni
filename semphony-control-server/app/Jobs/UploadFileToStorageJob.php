<?php

namespace App\Jobs;

use App\Models\ClientFile;
use App\Models\StorageConfiguration;
use App\Services\StorageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class UploadFileToStorageJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ClientFile $clientFile,
        public string $tempPath,
        public string $originalFilename,
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(StorageService $storageService): void
    {
        $storageConfiguration = $this->clientFile->storageConfiguration;

        if (! $storageConfiguration instanceof StorageConfiguration) {
            $this->fail(new \RuntimeException('Storage configuration not found'));

            return;
        }

        try {
            $remotePath = $storageService->uploadFile(
                $storageConfiguration,
                $this->tempPath,
                $this->originalFilename
            );

            // Update ClientFile with the remote path
            $this->clientFile->update([
                'storage_path' => $remotePath,
            ]);

            // Delete temporary file
            Storage::disk('local')->delete($this->tempPath);
        } catch (Throwable $e) {
            $this->fail($e);
        }
    }
}

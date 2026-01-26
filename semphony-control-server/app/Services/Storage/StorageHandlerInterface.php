<?php

namespace App\Services\Storage;

use App\Models\StorageConfiguration;

interface StorageHandlerInterface
{
    /**
     * Test connection to storage configuration.
     */
    public function testConnection(StorageConfiguration $configuration): bool;

    /**
     * Upload file to storage configuration.
     *
     * @return string The path where the file was stored
     */
    public function uploadFile(StorageConfiguration $configuration, string $localPath, string $remoteFilename): string;
}

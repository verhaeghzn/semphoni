<?php

namespace App\Services;

use App\Models\StorageConfiguration;
use App\Services\Storage\StorageHandlerFactory;
use Throwable;

class StorageService
{
    public function __construct(
        private readonly StorageHandlerFactory $factory
    ) {}

    /**
     * Test connection to storage configuration.
     */
    public function testConnection(StorageConfiguration $configuration): bool
    {
        try {
            $handler = $this->factory->make($configuration->type);

            return $handler->testConnection($configuration);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Upload file to storage configuration.
     *
     * @return string The path where the file was stored
     */
    public function uploadFile(StorageConfiguration $configuration, string $localPath, string $remoteFilename): string
    {
        $handler = $this->factory->make($configuration->type);

        return $handler->uploadFile($configuration, $localPath, $remoteFilename);
    }

    /**
     * Get all supported storage types.
     *
     * @return array<string>
     */
    public function supportedTypes(): array
    {
        return $this->factory->supportedTypes();
    }

    /**
     * Check if a storage type is supported.
     */
    public function isSupported(string $type): bool
    {
        return $this->factory->isSupported($type);
    }
}

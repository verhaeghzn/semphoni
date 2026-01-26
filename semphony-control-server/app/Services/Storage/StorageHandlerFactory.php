<?php

namespace App\Services\Storage;

use InvalidArgumentException;

class StorageHandlerFactory
{
    /**
     * Create a storage handler for the given type.
     */
    public function make(string $type): StorageHandlerInterface
    {
        return match ($type) {
            'sftp' => app(SftpStorageHandler::class),
            's3' => app(S3StorageHandler::class),
            default => throw new InvalidArgumentException("Unsupported storage type: {$type}"),
        };
    }

    /**
     * Get all supported storage types.
     *
     * @return array<string>
     */
    public function supportedTypes(): array
    {
        return ['sftp', 's3'];
    }

    /**
     * Check if a storage type is supported.
     */
    public function isSupported(string $type): bool
    {
        return in_array($type, $this->supportedTypes(), true);
    }
}

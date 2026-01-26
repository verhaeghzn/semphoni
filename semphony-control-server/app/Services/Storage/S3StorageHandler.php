<?php

namespace App\Services\Storage;

use App\Models\StorageConfiguration;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class S3StorageHandler implements StorageHandlerInterface
{
    /**
     * Test S3 connection.
     */
    public function testConnection(StorageConfiguration $configuration): bool
    {
        $config = $configuration->configuration;

        if (! is_array($config)) {
            return false;
        }

        $key = $config['key'] ?? null;
        $secret = $config['secret'] ?? null;
        $region = $config['region'] ?? null;
        $bucket = $config['bucket'] ?? null;

        if (! is_string($key) || ! is_string($secret) || ! is_string($region) || ! is_string($bucket)) {
            return false;
        }

        try {
            $diskName = 'test-s3-'.Str::random(8);
            $diskConfig = $this->buildDiskConfig($config, $key, $secret, $region, $bucket);

            config(['filesystems.disks.'.$diskName => $diskConfig]);

            // Try to list bucket to test connection
            Storage::disk($diskName)->files();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Upload file to S3.
     */
    public function uploadFile(StorageConfiguration $configuration, string $localPath, string $remoteFilename): string
    {
        $config = $configuration->configuration;

        if (! is_array($config)) {
            throw new \InvalidArgumentException('Invalid S3 configuration');
        }

        $key = $config['key'] ?? null;
        $secret = $config['secret'] ?? null;
        $region = $config['region'] ?? null;
        $bucket = $config['bucket'] ?? null;
        $directory = $config['directory'] ?? '';

        if (! is_string($key) || ! is_string($secret) || ! is_string($region) || ! is_string($bucket)) {
            throw new \InvalidArgumentException('Missing required S3 configuration');
        }

        $diskName = 's3-'.$configuration->id;
        $diskConfig = $this->buildDiskConfig($config, $key, $secret, $region, $bucket);

        config(['filesystems.disks.'.$diskName => $diskConfig]);

        $remotePath = $directory !== '' ? $directory.'/'.$remoteFilename : $remoteFilename;

        Storage::disk($diskName)->put($remotePath, Storage::disk('local')->get($localPath));

        return $remotePath;
    }

    /**
     * Build disk configuration array for S3.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function buildDiskConfig(array $config, string $key, string $secret, string $region, string $bucket): array
    {
        return [
            'driver' => 's3',
            'key' => $key,
            'secret' => $secret,
            'region' => $region,
            'bucket' => $bucket,
            'url' => $config['url'] ?? null,
            'endpoint' => $config['endpoint'] ?? null,
            'use_path_style_endpoint' => $config['use_path_style_endpoint'] ?? false,
        ];
    }
}

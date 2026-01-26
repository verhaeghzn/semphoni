<?php

namespace App\Services\Storage;

use App\Models\StorageConfiguration;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SftpStorageHandler implements StorageHandlerInterface
{
    /**
     * Test SFTP connection.
     */
    public function testConnection(StorageConfiguration $configuration): bool
    {
        $config = $configuration->configuration;

        if (! is_array($config)) {
            return false;
        }

        $host = $config['host'] ?? null;
        $username = $config['username'] ?? null;
        $password = $config['password'] ?? null;
        $port = $config['port'] ?? 22;
        $root = $config['root'] ?? '/';

        if (! is_string($host) || ! is_string($username)) {
            return false;
        }

        try {
            $diskName = 'test-sftp-'.Str::random(8);
            $diskConfig = $this->buildDiskConfig($config, $host, $username, $password, $port, $root);
            $diskConfig['timeout'] = 10;

            config(['filesystems.disks.'.$diskName => $diskConfig]);

            // Try to list directory to test connection
            Storage::disk($diskName)->files();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Upload file to SFTP.
     */
    public function uploadFile(StorageConfiguration $configuration, string $localPath, string $remoteFilename): string
    {
        $config = $configuration->configuration;

        if (! is_array($config)) {
            throw new \InvalidArgumentException('Invalid SFTP configuration');
        }

        $host = $config['host'] ?? null;
        $username = $config['username'] ?? null;
        $password = $config['password'] ?? null;
        $port = $config['port'] ?? 22;
        $root = $config['root'] ?? '/';
        $directory = $config['directory'] ?? '';

        if (! is_string($host) || ! is_string($username)) {
            throw new \InvalidArgumentException('Missing required SFTP configuration');
        }

        $diskName = 'sftp-'.$configuration->id;
        $diskConfig = $this->buildDiskConfig($config, $host, $username, $password, $port, $root);

        config(['filesystems.disks.'.$diskName => $diskConfig]);

        $remotePath = $directory !== '' ? $directory.'/'.$remoteFilename : $remoteFilename;

        Storage::disk($diskName)->put($remotePath, Storage::disk('local')->get($localPath));

        return $remotePath;
    }

    /**
     * Build disk configuration array for SFTP.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function buildDiskConfig(array $config, ?string $host, ?string $username, ?string $password, int $port, string $root): array
    {
        $diskConfig = [
            'driver' => 'sftp',
            'host' => $host,
            'username' => $username,
            'password' => $password,
            'port' => (int) $port,
            'root' => $root,
        ];

        if (isset($config['privateKey'])) {
            $diskConfig['privateKey'] = $config['privateKey'];
            unset($diskConfig['password']);
        }

        return $diskConfig;
    }
}

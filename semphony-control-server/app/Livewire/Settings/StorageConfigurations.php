<?php

namespace App\Livewire\Settings;

use App\Models\StorageConfiguration;
use App\Services\StorageService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class StorageConfigurations extends Component
{
    public ?int $editingId = null;

    // Form fields
    public string $name = '';

    public string $type = 'sftp';

    public bool $isActive = true;

    // SFTP fields
    public string $sftpHost = '';

    public string $sftpUsername = '';

    public string $sftpPassword = '';

    public int $sftpPort = 22;

    public string $sftpRoot = '/';

    public ?string $sftpDirectory = null;

    public ?string $sftpPrivateKey = null;

    // S3 fields
    public string $s3Key = '';

    public string $s3Secret = '';

    public string $s3Region = '';

    public string $s3Bucket = '';

    public ?string $s3Url = null;

    public ?string $s3Endpoint = null;

    public bool $s3UsePathStyle = false;

    public ?string $s3Directory = null;

    public ?string $testResult = null;

    public bool $testing = false;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        //
    }

    /**
     * Start editing a storage configuration.
     */
    public function edit(int $id): void
    {
        $config = StorageConfiguration::query()
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        $this->editingId = $config->id;
        $this->name = $config->name;
        $this->type = $config->type;
        $this->isActive = $config->is_active;

        $configData = $config->configuration ?? [];

        if ($config->type === 'sftp') {
            $this->sftpHost = $configData['host'] ?? '';
            $this->sftpUsername = $configData['username'] ?? '';
            $this->sftpPassword = $configData['password'] ?? '';
            $this->sftpPort = $configData['port'] ?? 22;
            $this->sftpRoot = $configData['root'] ?? '/';
            $this->sftpDirectory = $configData['directory'] ?? null;
            $this->sftpPrivateKey = $configData['privateKey'] ?? null;
        } elseif ($config->type === 's3') {
            $this->s3Key = $configData['key'] ?? '';
            $this->s3Secret = $configData['secret'] ?? '';
            $this->s3Region = $configData['region'] ?? '';
            $this->s3Bucket = $configData['bucket'] ?? '';
            $this->s3Url = $configData['url'] ?? null;
            $this->s3Endpoint = $configData['endpoint'] ?? null;
            $this->s3UsePathStyle = $configData['use_path_style_endpoint'] ?? false;
            $this->s3Directory = $configData['directory'] ?? null;
        }

        $this->testResult = null;
    }

    /**
     * Cancel editing.
     */
    public function cancel(): void
    {
        $this->resetForm();
    }

    /**
     * Delete a storage configuration.
     */
    public function delete(int $id): void
    {
        StorageConfiguration::query()
            ->where('user_id', Auth::id())
            ->findOrFail($id)
            ->delete();

        session()->flash('status', __('Storage configuration deleted.'));
    }

    /**
     * Save the storage configuration.
     */
    public function save(): void
    {
        $validated = $this->validate();

        $configuration = match ($this->type) {
            'sftp' => [
                'host' => $this->sftpHost,
                'username' => $this->sftpUsername,
                'password' => $this->sftpPassword,
                'port' => $this->sftpPort,
                'root' => $this->sftpRoot,
                'directory' => $this->sftpDirectory,
                ...($this->sftpPrivateKey ? ['privateKey' => $this->sftpPrivateKey] : []),
            ],
            's3' => [
                'key' => $this->s3Key,
                'secret' => $this->s3Secret,
                'region' => $this->s3Region,
                'bucket' => $this->s3Bucket,
                'url' => $this->s3Url,
                'endpoint' => $this->s3Endpoint,
                'use_path_style_endpoint' => $this->s3UsePathStyle,
                'directory' => $this->s3Directory,
            ],
            default => [],
        };

        if ($this->editingId) {
            $storageConfig = StorageConfiguration::query()
                ->where('user_id', Auth::id())
                ->findOrFail($this->editingId);

            $storageConfig->update([
                'name' => $validated['name'],
                'type' => $validated['type'],
                'configuration' => $configuration,
                'is_active' => $validated['isActive'],
            ]);
        } else {
            StorageConfiguration::query()->create([
                'user_id' => Auth::id(),
                'name' => $validated['name'],
                'type' => $validated['type'],
                'configuration' => $configuration,
                'is_active' => $validated['isActive'],
            ]);
        }

        $this->resetForm();
        session()->flash('status', __('Storage configuration saved.'));
    }

    /**
     * Test the storage connection.
     */
    public function testConnection(StorageService $storageService): void
    {
        $this->testing = true;
        $this->testResult = null;

        try {
            // Create a temporary configuration for testing
            $tempConfig = new StorageConfiguration([
                'type' => $this->type,
                'configuration' => match ($this->type) {
                    'sftp' => [
                        'host' => $this->sftpHost,
                        'username' => $this->sftpUsername,
                        'password' => $this->sftpPassword,
                        'port' => $this->sftpPort,
                        'root' => $this->sftpRoot,
                        'directory' => $this->sftpDirectory,
                        ...($this->sftpPrivateKey ? ['privateKey' => $this->sftpPrivateKey] : []),
                    ],
                    's3' => [
                        'key' => $this->s3Key,
                        'secret' => $this->s3Secret,
                        'region' => $this->s3Region,
                        'bucket' => $this->s3Bucket,
                        'url' => $this->s3Url,
                        'endpoint' => $this->s3Endpoint,
                        'use_path_style_endpoint' => $this->s3UsePathStyle,
                        'directory' => $this->s3Directory,
                    ],
                    default => [],
                },
            ]);

            $success = $storageService->testConnection($tempConfig);

            $this->testResult = $success
                ? 'success'
                : 'failed';
        } catch (\Throwable $e) {
            $this->testResult = 'failed';
        } finally {
            $this->testing = false;
        }
    }

    /**
     * Get validation rules.
     *
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:sftp,s3'],
            'isActive' => ['boolean'],
        ];

        if ($this->type === 'sftp') {
            $rules = array_merge($rules, [
                'sftpHost' => ['required', 'string', 'max:255'],
                'sftpUsername' => ['required', 'string', 'max:255'],
                'sftpPassword' => ['nullable', 'string'],
                'sftpPort' => ['required', 'integer', 'min:1', 'max:65535'],
                'sftpRoot' => ['required', 'string', 'max:255'],
                'sftpDirectory' => ['nullable', 'string', 'max:255'],
                'sftpPrivateKey' => ['nullable', 'string'],
            ]);
        } elseif ($this->type === 's3') {
            $rules = array_merge($rules, [
                's3Key' => ['required', 'string', 'max:255'],
                's3Secret' => ['required', 'string', 'max:255'],
                's3Region' => ['required', 'string', 'max:255'],
                's3Bucket' => ['required', 'string', 'max:255'],
                's3Url' => ['nullable', 'string', 'max:255'],
                's3Endpoint' => ['nullable', 'string', 'max:255'],
                's3UsePathStyle' => ['boolean'],
                's3Directory' => ['nullable', 'string', 'max:255'],
            ]);
        }

        return $rules;
    }

    /**
     * Reset the form.
     */
    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->type = 'sftp';
        $this->isActive = true;
        $this->sftpHost = '';
        $this->sftpUsername = '';
        $this->sftpPassword = '';
        $this->sftpPort = 22;
        $this->sftpRoot = '/';
        $this->sftpDirectory = null;
        $this->sftpPrivateKey = null;
        $this->s3Key = '';
        $this->s3Secret = '';
        $this->s3Region = '';
        $this->s3Bucket = '';
        $this->s3Url = null;
        $this->s3Endpoint = null;
        $this->s3UsePathStyle = false;
        $this->s3Directory = null;
        $this->testResult = null;
    }

    /**
     * Get storage configurations for the current user.
     */
    #[Computed]
    public function storageConfigurations()
    {
        return StorageConfiguration::query()
            ->where('user_id', Auth::id())
            ->orderBy('name')
            ->get();
    }
}

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Storage Configurations') }}</flux:heading>

    <x-settings.layout :heading="__('Storage Configurations')" :subheading="__('Configure external storage for client file uploads (SFTP or S3)')">
        <div class="my-6 w-full space-y-6">
            @if ($this->storageConfigurations->isEmpty() && ! $editingId)
                <flux:text class="text-gray-500">{{ __('No storage configurations yet. Create one to get started.') }}</flux:text>
            @endif

            @if (! $editingId)
                <div class="space-y-4">
                    @foreach ($this->storageConfigurations as $config)
                        <div class="flex items-center justify-between rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <flux:heading size="sm">{{ $config->name }}</flux:heading>
                                    @if ($config->is_active)
                                        <flux:badge variant="success" size="sm">{{ __('Active') }}</flux:badge>
                                    @else
                                        <flux:badge variant="subtle" size="sm">{{ __('Inactive') }}</flux:badge>
                                    @endif
                                    <flux:badge variant="subtle" size="sm">{{ strtoupper($config->type) }}</flux:badge>
                                </div>
                                <flux:text class="mt-1 text-sm text-gray-500">
                                    {{ $config->type === 'sftp' ? ($config->configuration['host'] ?? 'N/A') : ($config->configuration['bucket'] ?? 'N/A') }}
                                </flux:text>
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:button variant="ghost" size="sm" wire:click="edit({{ $config->id }})">
                                    {{ __('Edit') }}
                                </flux:button>
                                <flux:button variant="ghost" size="sm" wire:click="delete({{ $config->id }})" wire:confirm="{{ __('Are you sure you want to delete this storage configuration?') }}">
                                    {{ __('Delete') }}
                                </flux:button>
                            </div>
                        </div>
                    @endforeach

                    <flux:button variant="primary" wire:click="$set('editingId', null); $set('name', ''); $set('type', '');">
                        {{ __('Create New Storage Configuration') }}
                    </flux:button>
                </div>
            @endif

            @if ($editingId || $this->storageConfigurations->isEmpty())
                <form wire:submit="save" class="space-y-6">
                    <div class="space-y-4">
                        <flux:select wire:model.live="type" :label="__('Storage Type')" required>
                            <option value="">{{ __('Select storage type...') }}</option>
                            <option value="sftp">{{ __('SFTP') }}</option>
                            <option value="s3">{{ __('S3') }}</option>
                        </flux:select>

                        @if ($type)
                            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus placeholder="{{ __('e.g., Production SFTP Server') }}" />
                        @endif
                    </div>

                    @if ($type === 'sftp')
                        <div class="space-y-4 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <flux:heading size="sm">{{ __('SFTP Configuration') }}</flux:heading>

                            <flux:input wire:model="sftpHost" :label="__('Host')" type="text" required placeholder="sftp.example.com" />
                            <flux:input wire:model="sftpUsername" :label="__('Username')" type="text" required />
                            <flux:input wire:model="sftpPassword" :label="__('Password')" type="password" />
                            <flux:input wire:model="sftpPort" :label="__('Port')" type="number" min="1" max="65535" required />
                            <flux:input wire:model="sftpRoot" :label="__('Root Path')" type="text" required placeholder="/home/username" />
                            <flux:input wire:model="sftpDirectory" :label="__('Directory (optional)')" type="text" placeholder="uploads" />
                            <flux:textarea wire:model="sftpPrivateKey" :label="__('Private Key (optional, alternative to password)')" rows="4" />
                        </div>
                    @endif

                    @if ($type === 's3')
                        <div class="space-y-4 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <flux:heading size="sm">{{ __('S3 Configuration') }}</flux:heading>

                            <flux:input wire:model="s3Key" :label="__('Access Key ID')" type="text" required />
                            <flux:input wire:model="s3Secret" :label="__('Secret Access Key')" type="password" required />
                            <flux:input wire:model="s3Region" :label="__('Region')" type="text" required placeholder="us-east-1" />
                            <flux:input wire:model="s3Bucket" :label="__('Bucket')" type="text" required />
                            <flux:input wire:model="s3Directory" :label="__('Directory (optional)')" type="text" placeholder="uploads" />
                            <flux:input wire:model="s3Url" :label="__('URL (optional)')" type="text" />
                            <flux:input wire:model="s3Endpoint" :label="__('Endpoint (optional)')" type="text" />
                            <flux:checkbox wire:model="s3UsePathStyle" :label="__('Use Path Style Endpoint')" />
                        </div>
                    @endif

                    @if ($type)
                        <flux:checkbox wire:model="isActive" :label="__('Active')" />
                    @endif

                    @if ($type)
                        <div class="flex items-center gap-4">
                            <flux:button variant="primary" type="submit" :disabled="$testing">
                                {{ $editingId ? __('Update') : __('Create') }}
                            </flux:button>

                            <flux:button variant="ghost" type="button" wire:click="testConnection" :disabled="$testing">
                                @if ($testing)
                                    {{ __('Testing...') }}
                                @else
                                    {{ __('Test Connection') }}
                                @endif
                            </flux:button>

                            @if ($editingId)
                                <flux:button variant="ghost" type="button" wire:click="cancel">
                                    {{ __('Cancel') }}
                                </flux:button>
                            @endif

                            @if ($testResult === 'success')
                                <flux:text class="text-green-600">{{ __('Connection successful!') }}</flux:text>
                            @elseif ($testResult === 'failed')
                                <flux:text class="text-red-600">{{ __('Connection failed. Please check your configuration.') }}</flux:text>
                            @endif
                        </div>
                    @endif

                    <x-action-message class="me-3" on="status">
                        {{ session('status') }}
                    </x-action-message>
                </form>
            @endif
        </div>
    </x-settings.layout>
</section>

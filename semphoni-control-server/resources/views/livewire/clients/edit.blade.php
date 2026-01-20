<div class="max-w-2xl">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="lg">{{ __('Edit client') }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-600">
                {{ __('Update client configuration and API key.') }}
            </flux:text>
        </div>

        <flux:button variant="outline" :href="route('clients.index')" wire:navigate>
            {{ __('Back') }}
        </flux:button>
    </div>

    <form wire:submit="save" class="mt-6 space-y-6">
        <flux:select wire:model="systemId" :label="__('System')">
            @foreach ($systems as $system)
                <flux:select.option :value="$system->id">{{ $system->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:input wire:model="name" :label="__('Name')" type="text" required />

        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                        {{ __('Supported commands') }}
                    </div>
                    <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('Select the commands this client supports.') }}
                    </div>
                </div>
                <div class="text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __('Selected:') }} {{ count($supportedCommandIds) }}
                </div>
            </div>

            <div class="mt-4 grid gap-3">
                @foreach ($commands as $command)
                    <label class="flex items-start gap-3">
                        <input
                            type="checkbox"
                            class="mt-0.5 h-4 w-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:focus:ring-zinc-100"
                            wire:model="supportedCommandIds"
                            value="{{ $command->id }}"
                        />

                        <div class="grid gap-0.5">
                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $command->name }}
                            </div>
                            @if ($command->description)
                                <div class="text-sm text-zinc-600 dark:text-zinc-400">
                                    {{ $command->description }}
                                </div>
                            @endif
                        </div>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="widthPx" :label="__('Width (px)')" type="number" min="1" required />
            <flux:input wire:model="heightPx" :label="__('Height (px)')" type="number" min="1" required />
        </div>

        <flux:checkbox wire:model="canScreenshot" :label="__('Client can take screenshots')" />
        <flux:checkbox wire:model="isActive" :label="__('Enable websocket + heartbeat checks')" />

        <div class="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end">
            <flux:input wire:model="apiKey" :label="__('API key')" type="text" required autocomplete="off" />

            <flux:button variant="outline" type="button" wire:click="generateApiKey">
                {{ __('Regenerate') }}
            </flux:button>
        </div>

        <div class="flex items-center gap-3">
            <flux:button variant="primary" type="submit">
                {{ __('Save') }}
            </flux:button>

            <flux:button variant="outline" type="button" :href="route('clients.index')" wire:navigate>
                {{ __('Cancel') }}
            </flux:button>
        </div>
    </form>
</div>

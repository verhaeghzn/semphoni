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

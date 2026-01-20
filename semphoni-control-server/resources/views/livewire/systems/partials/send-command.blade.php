@if ($selectedClientIsOffline)
    <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-900 dark:border-red-900/40 dark:bg-red-950/40 dark:text-red-100">
        @if ($selectedClientOfflineSince)
            {{ __('Client is offline since') }}
            <time datetime="{{ $selectedClientOfflineSince->toIso8601String() }}" class="font-medium">
                {{ $selectedClientOfflineSince->toDayDateTimeString() }}
            </time>
        @else
            {{ __('Client is offline.') }}
        @endif
    </div>
@endif

@if (! $canControl)
    <flux:callout color="amber">
        <flux:callout.heading>{{ __('View-only') }}</flux:callout.heading>
        <flux:callout.text>
            {{ __('Another user currently controls this system. You can view, but you cannot send commands.') }}
        </flux:callout.text>
    </flux:callout>
@endif

<div class="grid gap-4">
    <flux:select wire:model.live="clientId" :label="__('Client')" :disabled="! $canControl || $selectedClientIsOffline">
        @foreach ($clients as $client)
            <flux:select.option :value="$client->id">
                {{ $client->name }}
            </flux:select.option>
        @endforeach
    </flux:select>

    <flux:select wire:model.live="commandId" :label="__('Command')" :disabled="! $canControl || $selectedClientIsOffline">
        @foreach ($commands as $command)
            <flux:select.option :value="$command->id">
                {{ $command->name }}
            </flux:select.option>
        @endforeach
    </flux:select>
</div>

<div class="flex items-center gap-2">
    <flux:button
        variant="primary"
        type="button"
        wire:click="dispatchSelectedCommand"
        wire:loading.attr="disabled"
        :disabled="! $canControl || $selectedClientIsOffline"
    >
        {{ __('Send') }}
    </flux:button>
</div>

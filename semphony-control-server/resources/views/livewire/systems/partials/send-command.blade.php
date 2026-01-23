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
    <flux:select wire:model.live="commandId" :label="__('Command')" :disabled="! $canControl || $selectedClientIsOffline">
        @foreach ($commands as $command)
            <flux:select.option :value="$command->id">
                {{ $command->name }}
            </flux:select.option>
        @endforeach
    </flux:select>

    <div class="space-y-2">
        <label class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
            {{ __('Action') }}
        </label>
        <div class="flex gap-2">
            <label class="flex-1">
                <input
                    type="radio"
                    class="peer sr-only"
                    wire:model="commandType"
                    value="clickButton"
                    :disabled="! $canControl || $selectedClientIsOffline"
                />
                <span
                    class="flex items-center justify-center rounded-md border border-neutral-200 bg-white px-3 py-2 text-sm text-zinc-900 transition-colors peer-checked:border-neutral-900 peer-checked:bg-neutral-900 peer-checked:text-white dark:border-neutral-800 dark:bg-neutral-950 dark:text-zinc-100 dark:peer-checked:border-white dark:peer-checked:bg-white dark:peer-checked:text-neutral-900 peer-disabled:cursor-not-allowed peer-disabled:opacity-50"
                >
                    {{ __('Click button') }}
                </span>
            </label>
            <label class="flex-1">
                <input
                    type="radio"
                    class="peer sr-only"
                    wire:model="commandType"
                    value="gotoButton"
                    :disabled="! $canControl || $selectedClientIsOffline"
                />
                <span
                    class="flex items-center justify-center rounded-md border border-neutral-200 bg-white px-3 py-2 text-sm text-zinc-900 transition-colors peer-checked:border-neutral-900 peer-checked:bg-neutral-900 peer-checked:text-white dark:border-neutral-800 dark:bg-neutral-950 dark:text-zinc-100 dark:peer-checked:border-white dark:peer-checked:bg-white dark:peer-checked:text-neutral-900 peer-disabled:cursor-not-allowed peer-disabled:opacity-50"
                >
                    {{ __('Move to button') }}
                </span>
            </label>
        </div>
    </div>
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

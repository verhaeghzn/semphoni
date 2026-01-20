<div
    class="rounded-xl border border-neutral-200 bg-white p-4 space-y-3 dark:border-neutral-800 dark:bg-neutral-950"
    @if ($visualFeedEnabled)
        wire:poll.{{ $visualFeedIntervalSeconds }}s="refreshScreenshot"
    @endif
>
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <flux:heading>
                    {{ __('Visual feed') }}
                    <span class="text-zinc-500">—</span>
                    {{ $clientName }}
                </flux:heading>

                @if ($clientIsOffline)
                    <flux:badge color="amber">{{ __('OFFLINE') }}</flux:badge>
                @endif
            </div>

            <flux:text class="mt-1 text-xs text-zinc-500">
                {{ __('Fetches a screenshot every :seconds seconds (monitor :monitor).', ['seconds' => $visualFeedIntervalSeconds, 'monitor' => $visualFeedMonitorNr]) }}
            </flux:text>
        </div>

        <div class="flex items-center gap-2">
            <flux:switch
                wire:model.live="visualFeedEnabled"
                :label="__('Live')"
                :disabled="! $canControl || $clientIsOffline"
            />
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <flux:input
            wire:model.live="visualFeedIntervalSeconds"
            :label="__('Interval (seconds)')"
            type="number"
            min="1"
            max="60"
            :disabled="! $canControl || $clientIsOffline"
        />

        <flux:input
            wire:model.live="visualFeedMonitorNr"
            :label="__('Monitor #')"
            type="number"
            min="1"
            max="3"
            :disabled="! $canControl || $clientIsOffline"
        />
    </div>

    @if (! $visualFeedEnabled && $lastScreenshotTakenAtIso)
        <flux:text class="text-xs text-zinc-500">
            {{ __('Last captured:') }}
            <time datetime="{{ $lastScreenshotTakenAtIso }}">
                {{ $lastScreenshotTakenAtLabel }}
            </time>
        </flux:text>
    @endif

    @if ($visualFeedEnabled)
        <div class="h-1 overflow-hidden rounded-full bg-neutral-200 dark:bg-neutral-800" role="progressbar" aria-label="{{ __('Visual feed refresh interval') }}">
            <div
                class="h-full origin-left bg-neutral-900 dark:bg-white"
                style="animation: visual-feed-interval {{ $visualFeedIntervalSeconds }}s linear infinite;"
            ></div>
        </div>
    @endif

    @if ($screenshotDataUrl)
        <img
            src="{{ $screenshotDataUrl }}"
            alt="{{ __('Screenshot') }}"
            class="w-full rounded-lg border border-neutral-200 bg-neutral-50 dark:border-neutral-800 dark:bg-neutral-900/30"
        />
    @else
        <flux:text class="text-sm text-zinc-600">
            @if ($clientIsOffline)
                {{ __('Client is offline.') }}
            @else
                {{ $visualFeedEnabled ? __('Waiting for screenshot…') : __('Visual feed is off.') }}
            @endif
        </flux:text>
    @endif
</div>

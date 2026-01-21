<div
    x-data="{
        clientId: {{ (int) $clientId }},
        systemId: {{ (int) $systemId }},
        enabled: @entangle('visualFeedEnabled').live,
        interval: @entangle('visualFeedIntervalSeconds').live,
        monitor: @entangle('visualFeedMonitorNr').live,
        offline: @entangle('clientIsOffline').live,
        canControl: @entangle('canControl').live,
        init() {
            const prefix = () => `[VisualFeed s:${this.systemId} c:${this.clientId}]`;

            console.debug(prefix(), 'init', {
                enabled: this.enabled,
                interval: this.interval,
                monitor: this.monitor,
                offline: this.offline,
                canControl: this.canControl,
            });

            this.$watch('enabled', (v) => console.debug(prefix(), 'enabled ->', v));
            this.$watch('interval', (v) => console.debug(prefix(), 'interval ->', v));
            this.$watch('monitor', (v) => console.debug(prefix(), 'monitor ->', v));
            this.$watch('offline', (v) => console.debug(prefix(), 'offline ->', v));
            this.$watch('canControl', (v) => console.debug(prefix(), 'canControl ->', v));
        },
    }"
>
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
                <flux:button
                    variant="outline"
                    size="sm"
                    type="button"
                    wire:click="toggleVisualFeedFullscreen"
                >
                    {{ __('Fullscreen') }}
                </flux:button>

                <flux:switch
                    wire:model.live="visualFeedEnabled"
                    :label="__('Live')"
                    :disabled="! $canControl || $clientIsOffline"
                />
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <div class="space-y-2">
                <flux:input
                    wire:model.live.number="visualFeedIntervalSeconds"
                    :label="__('Interval (seconds)')"
                    type="number"
                    min="1"
                    max="60"
                    :disabled="! $canControl || $clientIsOffline"
                />

                <div class="flex items-center gap-2">
                    <flux:button
                        variant="outline"
                        size="sm"
                        type="button"
                        wire:click="decrementVisualFeedInterval"
                        :disabled="! $canControl || $clientIsOffline"
                    >
                        −
                    </flux:button>

                    <flux:button
                        variant="outline"
                        size="sm"
                        type="button"
                        wire:click="incrementVisualFeedInterval"
                        :disabled="! $canControl || $clientIsOffline"
                    >
                        +
                    </flux:button>
                </div>
            </div>

            <fieldset class="space-y-2" aria-label="{{ __('Monitor') }}">
                <legend class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                    {{ __('Monitor') }}
                </legend>

                <div class="flex flex-wrap gap-2">
                    @for ($monitorNr = 1; $monitorNr <= $visualFeedMonitorMax; $monitorNr++)
                        <label wire:key="monitor-{{ $clientId }}-{{ $monitorNr }}" class="inline-flex">
                            <input
                                type="radio"
                                class="peer sr-only"
                                wire:model.live.number="visualFeedMonitorNr"
                                name="visualFeedMonitorNr-{{ $clientId }}"
                                value="{{ $monitorNr }}"
                                @disabled(! $canControl || $clientIsOffline)
                            />

                            <span
                                class="inline-flex items-center justify-center rounded-md border border-neutral-200 bg-white px-3 py-1.5 text-sm text-zinc-900 transition-colors peer-checked:border-neutral-900 peer-checked:bg-neutral-900 peer-checked:text-white dark:border-neutral-800 dark:bg-neutral-950 dark:text-zinc-100 dark:peer-checked:border-white dark:peer-checked:bg-white dark:peer-checked:text-neutral-900 peer-disabled:cursor-not-allowed peer-disabled:opacity-50"
                            >
                                {{ $monitorNr }}
                            </span>
                        </label>
                    @endfor
                </div>
            </fieldset>
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

    <div
        class="fixed inset-0 z-50 bg-black"
        wire:ignore.self
        x-data="{
            open: @entangle('visualFeedFullscreen'),
            init() {
                this.$watch('open', (value) => {
                    document.documentElement.classList.toggle('overflow-hidden', value);
                });
            },
        }"
        x-show="open"
        x-cloak
        x-on:keydown.escape.window="open = false"
    >
        <div class="absolute inset-0">
            @if ($screenshotDataUrl)
                <img
                    src="{{ $screenshotDataUrl }}"
                    alt="{{ __('Screenshot') }}"
                    class="h-full w-full object-contain"
                />
            @else
                <div class="flex h-full items-center justify-center p-6">
                    <flux:text class="text-sm text-white/80">
                        {{ __('No screenshot available.') }}
                    </flux:text>
                </div>
            @endif
        </div>

        <div class="absolute left-4 top-4 flex items-center gap-2">
            <flux:badge color="zinc">{{ __('Fullscreen') }}</flux:badge>
            <flux:badge color="zinc">{{ $clientName }}</flux:badge>

            @if ($clientIsOffline)
                <flux:badge color="amber">{{ __('OFFLINE') }}</flux:badge>
            @endif

            @if ($visualFeedEnabled)
                <flux:badge color="green">{{ __('Live') }}</flux:badge>
            @endif
        </div>

        <div class="absolute right-4 top-4">
            <flux:button variant="outline" size="sm" type="button" x-on:click="open = false">
                {{ __('Exit') }}
            </flux:button>
        </div>
    </div>
</div>

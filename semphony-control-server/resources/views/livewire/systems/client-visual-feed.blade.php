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
        class="relative rounded-xl border border-neutral-200 bg-neutral-50 dark:border-neutral-800 dark:bg-neutral-900/30 overflow-hidden"
        @if ($visualFeedEnabled)
            wire:poll.{{ $visualFeedIntervalSeconds }}s="refreshScreenshot"
        @endif
    >
        @if ($screenshotDataUrl)
            <img
                src="{{ $screenshotDataUrl }}"
                alt="{{ __('Screenshot') }}"
                class="w-full h-auto"
            />
        @else
            <div class="flex h-64 items-center justify-center">
                <flux:text class="text-sm text-zinc-600">
                    @if ($clientIsOffline)
                        {{ __('Client is offline.') }}
                    @else
                        {{ __('No screenshot available.') }}
                    @endif
                </flux:text>
            </div>
        @endif

        @if ($screenshotIsOld && $screenshotDataUrl)
            <div class="absolute inset-0 bg-amber-500/10 border-2 border-amber-500/50 pointer-events-none flex items-center justify-center">
                <div class="bg-amber-500/90 text-white px-4 py-2 rounded-lg backdrop-blur-sm">
                    <flux:text class="text-sm font-medium">
                        {{ __('Screenshot is old') }}
                    </flux:text>
                </div>
            </div>
        @endif

        <div class="absolute top-4 left-4 right-4 flex items-center justify-between gap-2 z-10">
            <div class="flex items-center gap-2 flex-wrap">
                <div class="flex items-center gap-2 bg-white/90 dark:bg-neutral-900/90 backdrop-blur-sm rounded-lg px-3 py-2 border border-neutral-200 dark:border-neutral-700">
                    <flux:button
                        variant="outline"
                        size="sm"
                        type="button"
                        wire:click="decrementVisualFeedInterval"
                        :disabled="! $canControl || $clientIsOffline"
                        class="!p-1"
                    >
                        −
                    </flux:button>

                    <flux:input
                        wire:model.live.number="visualFeedIntervalSeconds"
                        type="number"
                        min="1"
                        max="60"
                        :disabled="! $canControl || $clientIsOffline"
                        class="w-16 !p-1 text-center"
                        aria-label="{{ __('Interval (seconds)') }}"
                    />

                    <flux:button
                        variant="outline"
                        size="sm"
                        type="button"
                        wire:click="incrementVisualFeedInterval"
                        :disabled="! $canControl || $clientIsOffline"
                        class="!p-1"
                    >
                        +
                    </flux:button>
                </div>

                <div class="flex items-center gap-1 bg-white/90 dark:bg-neutral-900/90 backdrop-blur-sm rounded-lg px-2 py-2 border border-neutral-200 dark:border-neutral-700">
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
                                class="inline-flex items-center justify-center rounded-md border border-neutral-200 bg-white px-2 py-1 text-xs text-zinc-900 transition-colors peer-checked:border-neutral-900 peer-checked:bg-neutral-900 peer-checked:text-white dark:border-neutral-800 dark:bg-neutral-950 dark:text-zinc-100 dark:peer-checked:border-white dark:peer-checked:bg-white dark:peer-checked:text-neutral-900 peer-disabled:cursor-not-allowed peer-disabled:opacity-50"
                            >
                                {{ $monitorNr }}
                            </span>
                        </label>
                    @endfor
                </div>
            </div>

            <div class="flex items-center gap-2">
                <flux:button
                    variant="outline"
                    size="sm"
                    type="button"
                    wire:click="toggleVisualFeedFullscreen"
                    class="bg-white/90 dark:bg-neutral-900/90 backdrop-blur-sm"
                >
                    {{ __('Fullscreen') }}
                </flux:button>
            </div>
        </div>

        @if ($visualFeedEnabled)
            <div class="absolute bottom-0 left-0 right-0 h-1 overflow-hidden bg-neutral-200/50 dark:bg-neutral-800/50" role="progressbar" aria-label="{{ __('Visual feed refresh interval') }}">
                <div
                    class="h-full origin-left bg-neutral-900 dark:bg-white"
                    style="animation: visual-feed-interval {{ $visualFeedIntervalSeconds }}s linear infinite;"
                ></div>
            </div>
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

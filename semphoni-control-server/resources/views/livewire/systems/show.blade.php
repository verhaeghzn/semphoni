<div class="flex flex-col gap-6" wire:poll.10s="refreshLockStatus">
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="lg">{{ $system->name }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-600">
                {{ $system->description ?? __('System overview and command center.') }}
            </flux:text>

            @php
                $lockIsActive = $system->control_locked_by_user_id !== null
                    && $system->control_locked_until !== null
                    && $system->control_locked_until->isFuture();

                $lockOwnerName = $system->controlLockedBy?->name;
            @endphp

            <div class="mt-2 flex flex-wrap items-center gap-2">
                @if (! $lockIsActive)
                    <flux:badge color="zinc">{{ __('Not locked') }}</flux:badge>
                    <flux:text class="text-xs text-zinc-600">
                        {{ __('Control is available.') }}
                    </flux:text>
                @elseif ($canControl)
                    <flux:badge color="green">{{ __('Locked to you') }}</flux:badge>

                    <flux:button
                        variant="outline"
                        size="sm"
                        type="button"
                        wire:click="releaseControlLock"
                        wire:loading.attr="disabled"
                    >
                        {{ __('Release') }}
                    </flux:button>
                @else
                    <flux:badge color="amber">{{ __('Locked') }}</flux:badge>
                    <flux:text class="text-xs text-zinc-600">
                        {{ __('Locked by :name (view-only).', ['name' => $lockOwnerName ?? __('another user')]) }}
                    </flux:text>
                @endif

                @if ($lockIsActive && $system->control_locked_until)
                    <flux:text class="text-xs text-zinc-500">
                        {{ __('Auto-release:') }}
                        <time datetime="{{ $system->control_locked_until->toIso8601String() }}">
                            {{ $system->control_locked_until->toDayDateTimeString() }}
                        </time>
                    </flux:text>
                @endif
            </div>
        </div>

        <flux:button variant="outline" :href="route('systems.index')" wire:navigate>
            {{ __('Back') }}
        </flux:button>
    </div>

    <nav class="flex flex-wrap gap-2 border-b border-neutral-200 dark:border-neutral-800" role="tablist" aria-label="{{ __('System sections') }}">
        <button
            type="button"
            role="tab"
            aria-selected="{{ $tab === 'command-center' ? 'true' : 'false' }}"
            wire:click="selectTab('command-center')"
            @class([
                '-mb-px border-b-2 px-3 py-2 text-sm font-medium transition-colors focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-neutral-950',
                'border-transparent text-zinc-600 hover:border-neutral-300 hover:text-zinc-900 dark:text-zinc-300 dark:hover:border-neutral-700 dark:hover:text-white' => $tab !== 'command-center',
                'border-accent text-zinc-900 dark:text-white' => $tab === 'command-center',
            ])
        >
            {{ __('Command Center') }}
        </button>

        <button
            type="button"
            role="tab"
            aria-selected="{{ $tab === 'logs' ? 'true' : 'false' }}"
            wire:click="selectTab('logs')"
            @class([
                '-mb-px border-b-2 px-3 py-2 text-sm font-medium transition-colors focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-neutral-950',
                'border-transparent text-zinc-600 hover:border-neutral-300 hover:text-zinc-900 dark:text-zinc-300 dark:hover:border-neutral-700 dark:hover:text-white' => $tab !== 'logs',
                'border-accent text-zinc-900 dark:text-white' => $tab === 'logs',
            ])
        >
            {{ __('Logs') }}
        </button>

        <button
            type="button"
            role="tab"
            aria-selected="{{ $tab === 'clients' ? 'true' : 'false' }}"
            wire:click="selectTab('clients')"
            @class([
                '-mb-px border-b-2 px-3 py-2 text-sm font-medium transition-colors focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-neutral-950',
                'border-transparent text-zinc-600 hover:border-neutral-300 hover:text-zinc-900 dark:text-zinc-300 dark:hover:border-neutral-700 dark:hover:text-white' => $tab !== 'clients',
                'border-accent text-zinc-900 dark:text-white' => $tab === 'clients',
            ])
        >
            {{ __('Clients') }}
        </button>
    </nav>

    @if ($tab === 'command-center')
        <div class="grid gap-6 lg:grid-cols-4">
            <div class="space-y-6 lg:col-span-3">
                <div
                    class="rounded-xl border border-neutral-200 bg-white p-4 space-y-3 dark:border-neutral-800 dark:bg-neutral-950"
                    @if ($visualFeedEnabled)
                        wire:poll.{{ $visualFeedIntervalSeconds }}s="refreshScreenshot"
                    @endif
                    @if ($canControl)
                        wire:poll.60s="keepControlLockAlive"
                    @endif
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <flux:heading>{{ __('Visual feed') }}</flux:heading>
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
                                :disabled="! $canControl"
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
                            :disabled="! $canControl"
                        />

                        <flux:input
                            wire:model.live="visualFeedMonitorNr"
                            :label="__('Monitor #')"
                            type="number"
                            min="1"
                            max="16"
                            :disabled="! $canControl"
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
                            {{ $visualFeedEnabled ? __('Waiting for screenshot…') : __('Visual feed is off.') }}
                        </flux:text>
                    @endif
                </div>

                <div
                    class="fixed inset-0 z-50 bg-black"
                    wire:ignore.self
                    x-data="{
                        open: @entangle('visualFeedFullscreen'),
                        x: 16,
                        y: 16,
                        dragging: false,
                        startX: 0,
                        startY: 0,
                        originX: 0,
                        originY: 0,
                        init() {
                            this.$watch('open', (value) => {
                                document.documentElement.classList.toggle('overflow-hidden', value);

                                if (value) {
                                    this.$nextTick(() => this.clamp());
                                }
                            });

                            window.addEventListener('resize', () => this.clamp());
                        },
                        clamp() {
                            if (! this.$refs.panel) {
                                return;
                            }

                            const padding = 16;
                            const panelWidth = this.$refs.panel.offsetWidth;
                            const panelHeight = this.$refs.panel.offsetHeight;
                            const maxX = window.innerWidth - panelWidth - padding;
                            const maxY = window.innerHeight - panelHeight - padding;

                            this.x = Math.min(Math.max(this.x, padding), Math.max(maxX, padding));
                            this.y = Math.min(Math.max(this.y, padding), Math.max(maxY, padding));
                        },
                        startDrag(event) {
                            this.dragging = true;
                            this.startX = event.clientX;
                            this.startY = event.clientY;
                            this.originX = this.x;
                            this.originY = this.y;
                        },
                        moveDrag(event) {
                            if (! this.dragging) {
                                return;
                            }

                            this.x = this.originX + (event.clientX - this.startX);
                            this.y = this.originY + (event.clientY - this.startY);
                            this.clamp();
                        },
                        stopDrag() {
                            this.dragging = false;
                        },
                    }"
                    x-show="open"
                    x-cloak
                    x-on:mousemove.window="moveDrag($event)"
                    x-on:mouseup.window="stopDrag()"
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
                                    {{ $visualFeedEnabled ? __('Waiting for screenshot…') : __('Visual feed is off.') }}
                                </flux:text>
                            </div>
                        @endif
                    </div>

                    <div class="absolute left-4 top-4 flex items-center gap-2">
                        <flux:badge color="zinc">{{ __('Fullscreen') }}</flux:badge>
                        @if ($visualFeedEnabled)
                            <flux:badge color="green">{{ __('Live') }}</flux:badge>
                        @endif
                    </div>

                    <div class="absolute right-4 top-4">
                        <flux:button variant="outline" size="sm" type="button" x-on:click="open = false">
                            {{ __('Exit') }}
                        </flux:button>
                    </div>

                    <div
                        class="absolute left-0 top-0"
                        x-bind:style="`transform: translate3d(${x}px, ${y}px, 0)`"
                    >
                        <div
                            x-ref="panel"
                            class="w-[22rem] max-w-[calc(100vw-2rem)] rounded-xl border border-neutral-200 bg-white/95 p-4 shadow-lg backdrop-blur dark:border-neutral-800 dark:bg-neutral-950/95"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <button
                                    type="button"
                                    class="cursor-move select-none text-sm font-semibold text-zinc-900 dark:text-white"
                                    x-on:mousedown.prevent="startDrag($event)"
                                >
                                    {{ __('Send command') }}
                                </button>

                                <flux:button variant="ghost" size="sm" type="button" x-on:click="open = false">
                                    {{ __('Close') }}
                                </flux:button>
                            </div>

                            <div class="mt-4 space-y-4">
                                @include('livewire.systems.partials.send-command')
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if (! $visualFeedFullscreen)
                <div class="space-y-6 lg:col-span-1">
                    <div class="rounded-xl border border-neutral-200 bg-white p-4 space-y-4 dark:border-neutral-800 dark:bg-neutral-950">
                        <div class="flex items-center justify-between gap-4">
                            <flux:heading>{{ __('Send command') }}</flux:heading>
                        </div>

                        @include('livewire.systems.partials.send-command')
                    </div>

                    <div class="rounded-xl border border-neutral-200 bg-white p-4 space-y-3 dark:border-neutral-800 dark:bg-neutral-950">
                        <flux:heading>{{ __('Response (raw JSON)') }}</flux:heading>

                        @if ($lastCommandCorrelationId && ! $lastResponseJson)
                            <div wire:poll.2s="refreshLatestResponse"></div>
                        @endif

                        @if ($lastResponseJson)
                            <pre class="overflow-auto rounded-lg border border-neutral-200 bg-neutral-50 p-3 text-xs dark:border-neutral-800 dark:bg-neutral-900/30">{{ $lastResponseJson }}</pre>
                        @else
                            <flux:text class="text-sm text-zinc-600">
                                {{ __('No response yet.') }}
                            </flux:text>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    @endif

    @if ($tab === 'logs')
        <div class="flex items-center justify-between gap-4">
            <div>
                <flux:heading size="lg">{{ __('Logs') }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-600">
                    {{ __('Latest 200 logs for this system.') }}
                </flux:text>
            </div>

            <flux:checkbox wire:model.live="showHeartbeats" :label="__('Show heartbeats')" />
        </div>

        <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white">
            <div class="divide-y divide-neutral-200">
                @forelse ($logs as $log)
                    <div class="p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:badge>{{ $log->direction->value }}</flux:badge>
                                <flux:text class="text-sm text-zinc-600">
                                    {{ $log->client->name }}
                                </flux:text>
                                @if ($log->command)
                                    <flux:badge color="zinc">{{ $log->command->name }}</flux:badge>
                                @endif
                            </div>

                            <flux:text class="text-xs text-zinc-500">
                                {{ $log->created_at->diffForHumans() }}
                            </flux:text>
                        </div>

                        <flux:text class="mt-2 text-sm text-zinc-800">
                            {{ $log->summary }}
                        </flux:text>
                    </div>
                @empty
                    <div class="p-6">
                        <flux:text class="text-sm text-zinc-600">
                            {{ __('No logs yet.') }}
                        </flux:text>
                    </div>
                @endforelse
            </div>
        </div>
    @endif

    @if ($tab === 'clients')
        <div class="flex items-center justify-between gap-4">
            <div>
                <flux:heading size="lg">{{ __('Clients') }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-600">
                    {{ __('Clients registered under this system.') }}
                </flux:text>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white">
            <div class="divide-y divide-neutral-200">
                @forelse ($clients as $client)
                    <div class="flex items-start justify-between gap-4 p-4">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="mt-1 inline-block size-2 rounded-full {{ $client->is_active ? ($client->isActive() ? 'bg-green-500' : 'bg-red-500') : 'bg-zinc-400' }}" aria-hidden="true"></span>
                                <flux:heading>{{ $client->name }}</flux:heading>
                            </div>

                            <flux:text class="mt-1 text-sm text-zinc-600">
                                {{ $client->width_px }}×{{ $client->height_px }}
                                · {{ $client->can_screenshot ? __('Can screenshot') : __('No screenshot') }}
                                @if (! $client->is_active)
                                    · {{ __('Websocket disabled') }}
                                @endif
                            </flux:text>

                            <flux:text class="mt-1 truncate text-sm text-zinc-600">
                                {{ $client->latestNonHeartbeatLog?->summary ?? __('No activity yet') }}
                            </flux:text>
                        </div>

                        @can('clients.manage')
                            <flux:button variant="outline" size="sm" :href="route('clients.edit', $client)" wire:navigate>
                                {{ __('Edit') }}
                            </flux:button>
                        @endcan
                    </div>
                @empty
                    <div class="p-6">
                        <flux:text class="text-sm text-zinc-600">
                            {{ __('No clients yet.') }}
                        </flux:text>
                    </div>
                @endforelse
            </div>
        </div>
    @endif
</div>

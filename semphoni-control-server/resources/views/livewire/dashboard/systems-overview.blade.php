<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
    <div class="grid gap-4 md:grid-cols-2">
        @forelse ($systems as $system)
            <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white">
                <div class="border-b border-neutral-200 p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <flux:heading size="lg">{{ $system->name }}</flux:heading>

                            @if ($system->description)
                                <flux:text class="mt-1 text-sm text-zinc-600">
                                    {{ $system->description }}
                                </flux:text>
                            @endif
                        </div>

                        <flux:button variant="primary" size="sm" :href="route('systems.show', $system)" wire:navigate>
                            {{ __('Control') }}
                        </flux:button>
                    </div>
                </div>

                <div class="divide-y divide-neutral-200">
                    @forelse ($system->clients as $client)
                        <div class="flex items-start gap-3 px-4 py-3">
                            <span
                                class="mt-1 inline-block size-2 rounded-full {{ $client->isActive() ? 'bg-green-500' : 'bg-red-500' }}"
                                aria-hidden="true"
                            ></span>

                            <div class="min-w-0 flex-1">
                                <flux:text class="font-medium">
                                    {{ $client->name }}
                                </flux:text>

                                <flux:text class="mt-0.5 truncate text-sm text-zinc-600">
                                    {{ $client->latestNonHeartbeatLog?->summary ?? __('No activity yet') }}
                                </flux:text>
                            </div>

                            <flux:text class="shrink-0 text-xs text-zinc-500">
                                {{ $client->latestNonHeartbeatLog?->created_at?->diffForHumans() ?? '—' }}
                            </flux:text>
                        </div>
                    @empty
                        <div class="px-4 py-3">
                            <flux:text class="text-sm text-zinc-600">
                                {{ __('No clients yet') }}
                            </flux:text>
                        </div>
                    @endforelse
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-neutral-200 p-6">
                <flux:text class="text-sm text-zinc-600">
                    {{ __('No systems yet') }}
                </flux:text>
            </div>
        @endforelse
    </div>
</div>


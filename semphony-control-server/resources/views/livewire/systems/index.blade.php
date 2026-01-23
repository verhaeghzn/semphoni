<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="lg">{{ __('Systems') }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-600">
                {{ __('Microscope systems and their connected clients.') }}
            </flux:text>
        </div>

        @can('systems.manage')
            <flux:button variant="primary" :href="route('systems.create')" wire:navigate>
                {{ __('New system') }}
            </flux:button>
        @endcan
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        @forelse ($systems as $system)
            <div class="rounded-xl border border-neutral-200 bg-white p-4">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                            <flux:heading>
                                <a class="hover:underline" href="{{ route('systems.show', $system) }}" wire:navigate>
                                    {{ $system->name }}
                                </a>
                            </flux:heading>
                        <flux:text class="mt-1 text-sm text-zinc-600">
                            {{ trans_choice('{0} No clients|{1} :count client|[2,*] :count clients', $system->clients_count, ['count' => $system->clients_count]) }}
                        </flux:text>
                    </div>

                    <div class="flex items-center gap-2">
                        <flux:button variant="primary" size="sm" :href="route('systems.show', $system)" wire:navigate>
                            {{ __('Control') }}
                        </flux:button>

                        @can('systems.manage')
                            <flux:button variant="outline" size="sm" :href="route('systems.edit', $system)" wire:navigate>
                                {{ __('Edit') }}
                            </flux:button>
                        @endcan
                    </div>
                </div>

                @if ($system->description)
                    <flux:text class="mt-3 text-sm text-zinc-700">
                        {{ $system->description }}
                    </flux:text>
                @endif
            </div>
        @empty
            <div class="rounded-xl border border-neutral-200 p-6">
                <flux:text class="text-sm text-zinc-600">
                    {{ __('No systems yet.') }}
                </flux:text>
            </div>
        @endforelse
    </div>
</div>

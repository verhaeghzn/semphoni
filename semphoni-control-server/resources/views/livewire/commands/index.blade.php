<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="lg">{{ __('Commands') }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-600">
                {{ __('Available actions that can be sent to clients, with per-role access.') }}
            </flux:text>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white">
        <div class="divide-y divide-neutral-200">
            @forelse ($commands as $command)
                @php
                    $roles = $permissionToRoles->get($command->permissionName(), []);
                @endphp

                <div class="flex items-start justify-between gap-4 p-4">
                    <div class="min-w-0">
                        <flux:heading>{{ $command->name }}</flux:heading>
                        <flux:text class="mt-1 text-sm text-zinc-600">
                            {{ __('Type: :type', ['type' => $command->action_type->value]) }}
                        </flux:text>

                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            @foreach ($roles as $roleName)
                                <flux:badge>{{ $roleName }}</flux:badge>
                            @endforeach

                            @if (count($roles) === 0)
                                <flux:badge color="zinc">{{ __('No roles') }}</flux:badge>
                            @endif
                        </div>

                        @if ($command->description)
                            <flux:text class="mt-2 text-sm text-zinc-700">
                                {{ $command->description }}
                            </flux:text>
                        @endif
                    </div>

                    <flux:button variant="outline" size="sm" :href="route('commands.edit', $command)" wire:navigate>
                        {{ __('Edit') }}
                    </flux:button>
                </div>
            @empty
                <div class="p-6">
                    <flux:text class="text-sm text-zinc-600">
                        {{ __('No commands yet.') }}
                    </flux:text>
                </div>
            @endforelse
        </div>
    </div>
</div>

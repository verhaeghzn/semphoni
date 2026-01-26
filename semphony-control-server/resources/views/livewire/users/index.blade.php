<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Accounts') }}</flux:heading>

    <x-settings.layout :heading="__('Accounts')" :subheading="__('Manage user accounts and roles.')">
        <div class="my-6 flex flex-col gap-6">
            <div class="flex justify-end">
                <flux:button variant="primary" :href="route('users.create')" wire:navigate>
                    {{ __('New account') }}
                </flux:button>
            </div>

            <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white">
                <div class="divide-y divide-neutral-200">
                    @forelse ($users as $user)
                        <div class="flex items-start justify-between gap-4 p-4">
                            <div class="min-w-0">
                                <flux:heading class="truncate">{{ $user->name }}</flux:heading>

                                <flux:text class="mt-1 truncate text-sm text-zinc-600">
                                    {{ $user->email }}
                                </flux:text>

                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    @foreach ($user->roles as $role)
                                        <flux:badge>{{ $role->name }}</flux:badge>
                                    @endforeach

                                    @if ($user->roles->count() === 0)
                                        <flux:badge color="zinc">{{ __('No roles') }}</flux:badge>
                                    @endif

                                    @if ($user->two_factor_secret)
                                        <flux:badge color="green">{{ __('2FA enabled') }}</flux:badge>
                                    @else
                                        <flux:badge color="zinc">{{ __('2FA disabled') }}</flux:badge>
                                    @endif
                                </div>
                            </div>

                            <flux:button variant="outline" size="sm" :href="route('users.edit', $user)" wire:navigate>
                                {{ __('Edit') }}
                            </flux:button>
                        </div>
                    @empty
                        <div class="p-6">
                            <flux:text class="text-sm text-zinc-600">
                                {{ __('No accounts yet.') }}
                            </flux:text>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </x-settings.layout>
</section>


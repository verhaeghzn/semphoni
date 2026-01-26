<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Edit command') }}</flux:heading>

    <x-settings.layout :heading="__('Edit command')" :subheading="__('Control which roles may execute this command.')">
        <div class="my-6 max-w-2xl">
            <div class="mb-6 flex justify-end">
                <flux:button variant="outline" :href="route('commands.index')" wire:navigate>
                    {{ __('Back') }}
                </flux:button>
            </div>

            <div class="space-y-4 rounded-xl border border-neutral-200 bg-white p-4">
                <div class="grid gap-2">
                    <flux:text class="text-sm text-zinc-600">{{ __('Name') }}</flux:text>
                    <flux:text class="font-medium">{{ $name }}</flux:text>
                </div>

                <div class="grid gap-2">
                    <flux:text class="text-sm text-zinc-600">{{ __('Type') }}</flux:text>
                    <flux:text class="font-medium">{{ $actionType }}</flux:text>
                </div>
            </div>

            <form wire:submit="save" class="mt-6 space-y-6">
                <flux:textarea wire:model="description" :label="__('Description')" rows="4" />

                <div class="rounded-xl border border-neutral-200 bg-white p-4">
                    <flux:heading>{{ __('Allowed roles') }}</flux:heading>

                    <div class="mt-4 space-y-3">
                        @foreach ($roles as $role)
                            <flux:checkbox wire:model="roleAccess.{{ $role->name }}" :label="$role->name" />
                        @endforeach
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <flux:button variant="primary" type="submit">
                        {{ __('Save') }}
                    </flux:button>

                    <flux:button variant="outline" type="button" :href="route('commands.index')" wire:navigate>
                        {{ __('Cancel') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </x-settings.layout>
</section>

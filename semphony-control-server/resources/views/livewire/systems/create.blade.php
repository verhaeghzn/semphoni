<div class="max-w-2xl">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="lg">{{ __('New system') }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-600">
                {{ __('Create a microscope system.') }}
            </flux:text>
        </div>

        <flux:button variant="outline" :href="route('systems.index')" wire:navigate>
            {{ __('Back') }}
        </flux:button>
    </div>

    <form wire:submit="save" class="mt-6 space-y-6">
        <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus />
        <flux:textarea wire:model="description" :label="__('Description')" rows="4" />

        <div class="flex items-center gap-3">
            <flux:button variant="primary" type="submit">
                {{ __('Save') }}
            </flux:button>

            <flux:button variant="outline" type="button" :href="route('systems.index')" wire:navigate>
                {{ __('Cancel') }}
            </flux:button>
        </div>
    </form>
</div>

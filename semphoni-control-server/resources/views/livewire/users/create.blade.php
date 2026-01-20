<div class="max-w-2xl">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="lg">{{ __('New account') }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-600">
                {{ __('Create a user account.') }}
            </flux:text>
        </div>

        <flux:button variant="outline" :href="route('users.index')" wire:navigate>
            {{ __('Back') }}
        </flux:button>
    </div>

    <form wire:submit="save" class="mt-6 space-y-6">
        <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />
        <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

        <flux:select wire:model="role" :label="__('Role')">
            <flux:select.option value="User">{{ __('User') }}</flux:select.option>
            <flux:select.option value="Admin">{{ __('Admin') }}</flux:select.option>
        </flux:select>

        <flux:checkbox wire:model="emailVerified" :label="__('Mark email as verified')" />

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="password" :label="__('Password')" type="password" required autocomplete="new-password" />
            <flux:input wire:model="password_confirmation" :label="__('Confirm password')" type="password" required autocomplete="new-password" />
        </div>

        <div class="flex items-center gap-3">
            <flux:button variant="primary" type="submit">
                {{ __('Save') }}
            </flux:button>

            <flux:button variant="outline" type="button" :href="route('users.index')" wire:navigate>
                {{ __('Cancel') }}
            </flux:button>
        </div>
    </form>
</div>


<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Edit account') }}</flux:heading>

    <x-settings.layout :heading="__('Edit account')" :subheading="__('Update account details and security settings.')">
        <div class="my-6 max-w-2xl space-y-8">
            <div class="flex justify-end">
                <flux:button variant="outline" :href="route('users.index')" wire:navigate>
                    {{ __('Back') }}
                </flux:button>
            </div>

            <form wire:submit="save" class="space-y-6">
                <flux:input wire:model="name" :label="__('Name')" type="text" required autocomplete="name" />
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                <flux:select wire:model="role" :label="__('Role')">
                    <flux:select.option value="User">{{ __('User') }}</flux:select.option>
                    <flux:select.option value="Admin">{{ __('Admin') }}</flux:select.option>
                </flux:select>

                <flux:checkbox wire:model="emailVerified" :label="__('Email verified')" />

                <div class="space-y-2">
                    <flux:heading>{{ __('Reset password (optional)') }}</flux:heading>
                    <flux:text class="text-sm text-zinc-600">
                        {{ __('Leave empty to keep the current password.') }}
                    </flux:text>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="newPassword" :label="__('New password')" type="password" autocomplete="new-password" />
                    <flux:input wire:model="newPassword_confirmation" :label="__('Confirm new password')" type="password" autocomplete="new-password" />
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

            <div class="rounded-xl border border-neutral-200 bg-white p-6 space-y-4">
        <div class="flex items-center justify-between gap-4">
            <div>
                <flux:heading>{{ __('Two-factor authentication') }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-600">
                    {{ __('If needed, you can reset two-factor configuration for this user.') }}
                </flux:text>
            </div>

            @if ($this->hasTwoFactorEnabled)
                <flux:badge color="green">{{ __('Enabled') }}</flux:badge>
            @else
                <flux:badge color="zinc">{{ __('Disabled') }}</flux:badge>
            @endif
        </div>

        @if ($this->hasTwoFactorEnabled)
            <div class="flex justify-end">
                <flux:button variant="outline" type="button" wire:click="resetTwoFactor">
                    {{ __('Reset 2FA') }}
                </flux:button>
            </div>
        @endif
            </div>

            @if (! $this->isSelf)
                <div class="rounded-xl border border-red-200 bg-white p-6 space-y-4">
            <div>
                <flux:heading>{{ __('Delete account') }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-600">
                    {{ __('This will permanently delete the user and detach their roles. To confirm, enter your password.') }}
                </flux:text>
            </div>

            <flux:modal.trigger name="confirm-account-deletion">
                <flux:button variant="danger" x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-account-deletion')">
                    {{ __('Delete account') }}
                </flux:button>
            </flux:modal.trigger>

            <flux:modal name="confirm-account-deletion" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
                <form wire:submit="deleteUser" class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Confirm deletion') }}</flux:heading>
                        <flux:subheading>
                            {{ __('Enter your password to permanently delete this account.') }}
                        </flux:subheading>
                    </div>

                    <flux:input wire:model="currentPassword" :label="__('Password')" type="password" autocomplete="current-password" />

                    <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                        <flux:modal.close>
                            <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                        </flux:modal.close>

                        <flux:button variant="danger" type="submit">
                            {{ __('Delete account') }}
                        </flux:button>
                    </div>
                </form>
            </flux:modal>
        </div>
    @endif
</div>


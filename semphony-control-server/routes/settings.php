<?php

use App\Livewire\Commands\Edit as CommandsEdit;
use App\Livewire\Commands\Index as CommandsIndex;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use App\Livewire\Settings\StorageConfigurations;
use App\Livewire\Settings\TwoFactor;
use App\Livewire\Users\Create as UsersCreate;
use App\Livewire\Users\Edit as UsersEdit;
use App\Livewire\Users\Index as UsersIndex;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::middleware(['auth', '2fa'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::livewire('settings/profile', Profile::class)->name('profile.edit');
    Route::livewire('settings/storage-configurations', StorageConfigurations::class)->name('storage-configurations.index');
});

Route::middleware(['auth', 'verified', '2fa'])->group(function () {
    Route::livewire('settings/password', Password::class)->name('user-password.edit');

    Route::livewire('settings/commands', CommandsIndex::class)->name('commands.index');
    Route::livewire('settings/commands/{command}/edit', CommandsEdit::class)->name('commands.edit');

    Route::middleware('can:users.manage')->group(function () {
        Route::livewire('settings/accounts', UsersIndex::class)->name('users.index');
        Route::livewire('settings/accounts/create', UsersCreate::class)->name('users.create');
        Route::livewire('settings/accounts/{user}/edit', UsersEdit::class)->name('users.edit');
    });
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('settings/two-factor', TwoFactor::class)
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');
});

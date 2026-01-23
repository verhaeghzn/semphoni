<?php

namespace App\Livewire\Users;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Edit extends Component
{
    use AuthorizesRequests;
    use PasswordValidationRules;
    use ProfileValidationRules;

    public int $userId;

    public string $name = '';

    public string $email = '';

    public string $role = 'User';

    public bool $emailVerified = true;

    public string $newPassword = '';

    public string $newPassword_confirmation = '';

    public string $currentPassword = '';

    public function mount(User $user): void
    {
        $this->authorize('users.manage');

        $this->userId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->roles->first()?->name ?? 'User';
        $this->emailVerified = $user->email_verified_at !== null;
    }

    #[Computed]
    public function isSelf(): bool
    {
        return Auth::id() === $this->userId;
    }

    #[Computed]
    public function hasTwoFactorEnabled(): bool
    {
        return User::query()
            ->whereKey($this->userId)
            ->whereNotNull('two_factor_secret')
            ->exists();
    }

    public function save(): void
    {
        $this->authorize('users.manage');

        $validated = $this->validate([
            ...$this->profileRules($this->userId),
            'role' => ['required', 'string', 'in:User,Admin'],
            'emailVerified' => ['boolean'],
        ]);

        /** @var User $user */
        $user = User::query()->whereKey($this->userId)->firstOrFail();

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        if ($validated['emailVerified'] && $user->email_verified_at === null) {
            $user->email_verified_at = now();
        }

        if (! $validated['emailVerified']) {
            $user->email_verified_at = null;
        }

        $user->save();
        $user->syncRoles([$validated['role']]);

        if (filled($this->newPassword)) {
            $this->validate([
                'newPassword' => ['required', 'string', Password::default(), 'confirmed'],
            ]);

            $user->forceFill([
                'password' => $this->newPassword,
            ])->save();

            $this->newPassword = '';
            $this->newPassword_confirmation = '';
        }

        session()->flash('status', __('Account updated.'));

        $this->redirect(route('users.index', absolute: false), navigate: true);
    }

    public function resetTwoFactor(): void
    {
        $this->authorize('users.manage');

        User::query()
            ->whereKey($this->userId)
            ->update([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ]);

        session()->flash('status', __('Two-factor authentication reset.'));
    }

    public function deleteUser(): void
    {
        $this->authorize('users.manage');

        if ($this->isSelf) {
            abort(403);
        }

        $this->validate([
            'currentPassword' => $this->currentPasswordRules(),
        ], [], [
            'currentPassword' => __('password'),
        ]);

        /** @var User $user */
        $user = User::query()->whereKey($this->userId)->firstOrFail();
        $user->syncRoles([]);
        $user->delete();

        $this->currentPassword = '';

        session()->flash('status', __('Account deleted.'));

        $this->redirect(route('users.index', absolute: false), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.users.edit', [
            'user' => User::query()->with('roles')->whereKey($this->userId)->firstOrFail(),
        ]);
    }
}


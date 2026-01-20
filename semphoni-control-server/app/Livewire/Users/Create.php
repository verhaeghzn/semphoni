<?php

namespace App\Livewire\Users;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class Create extends Component
{
    use AuthorizesRequests;
    use PasswordValidationRules;
    use ProfileValidationRules;

    public string $name = '';

    public string $email = '';

    public string $role = 'User';

    public bool $emailVerified = true;

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $this->authorize('users.manage');
    }

    public function save(): void
    {
        $this->authorize('users.manage');

        $validated = $this->validate([
            ...$this->profileRules(),
            'role' => ['required', 'string', 'in:User,Admin'],
            'emailVerified' => ['boolean'],
            'password' => $this->passwordRules(),
        ]);

        /** @var User $user */
        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        $user->forceFill([
            'email_verified_at' => $validated['emailVerified'] ? now() : null,
        ])->save();

        $user->syncRoles([$validated['role']]);

        session()->flash('status', __('Account created.'));

        $this->redirect(route('users.index', absolute: false), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.users.create');
    }
}


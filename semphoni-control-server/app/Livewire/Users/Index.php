<?php

namespace App\Livewire\Users;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class Index extends Component
{
    use AuthorizesRequests;

    public function mount(): void
    {
        $this->authorize('users.manage');
    }

    public function render(): View
    {
        $this->authorize('users.manage');

        return view('livewire.users.index', [
            'users' => User::query()
                ->with('roles')
                ->orderBy('name')
                ->get(),
        ]);
    }
}


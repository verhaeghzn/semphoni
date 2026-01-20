<?php

namespace App\Livewire\Systems;

use App\Models\System;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class Create extends Component
{
    use AuthorizesRequests;

    public string $name = '';

    public ?string $description = null;

    public function mount(): void
    {
        $this->authorize('systems.manage');
    }

    public function save(): void
    {
        $this->authorize('systems.manage');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        System::query()->create($validated);

        session()->flash('status', __('System created.'));

        $this->redirect(route('systems.index', absolute: false), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.systems.create');
    }
}

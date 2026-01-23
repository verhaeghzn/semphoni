<?php

namespace App\Livewire\Systems;

use App\Models\System;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class Edit extends Component
{
    use AuthorizesRequests;

    public int $systemId;

    public string $name = '';

    public ?string $description = null;

    public function mount(System $system): void
    {
        $this->authorize('systems.manage');

        $this->systemId = $system->id;
        $this->name = $system->name;
        $this->description = $system->description;
    }

    public function save(): void
    {
        $this->authorize('systems.manage');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        System::query()
            ->whereKey($this->systemId)
            ->update($validated);

        session()->flash('status', __('System updated.'));

        $this->redirect(route('systems.index', absolute: false), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.systems.edit');
    }
}

<?php

namespace App\Livewire\Systems;

use App\Models\System;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Index extends Component
{
    public function render(): View
    {
        return view('livewire.systems.index', [
            'systems' => System::query()
                ->withCount('clients')
                ->orderBy('name')
                ->get(),
        ]);
    }
}

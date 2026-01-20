<?php

namespace App\Livewire\Dashboard;

use App\Models\System;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class SystemsOverview extends Component
{
    public function render(): View
    {
        return view('livewire.dashboard.systems-overview', [
            'systems' => System::query()
                ->with(['clients.latestLog', 'clients.latestNonHeartbeatLog'])
                ->orderBy('name')
                ->get(),
        ]);
    }
}


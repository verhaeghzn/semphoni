<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Index extends Component
{
    public function render(): View
    {
        return view('livewire.clients.index', [
            'clients' => Client::query()
                ->with(['system', 'latestLog'])
                ->orderBy('name')
                ->get(),
        ]);
    }
}

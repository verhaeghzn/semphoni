<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Models\ClientType;
use App\Models\System;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Component;

class Create extends Component
{
    use AuthorizesRequests;

    public int $systemId;

    public ?int $clientTypeId = null;

    public bool $addCommandSet = false;

    public string $name = '';

    public string $apiKey = '';

    public int $widthPx = 1920;

    public int $heightPx = 1080;

    public bool $canScreenshot = false;

    public bool $isActive = true;

    /**
     * @var Collection<int, System>
     */
    public Collection $systems;

    /**
     * @var Collection<int, ClientType>
     */
    public Collection $clientTypes;

    public function mount(): void
    {
        $this->authorize('clients.manage');

        $this->systems = System::query()->orderBy('name')->get();
        $this->systemId = $this->systems->first()?->id ?? 0;

        $this->clientTypes = ClientType::query()->orderBy('name')->get();
        $this->clientTypeId = $this->clientTypes->first()?->id;

        if ($this->systemId === 0) {
            session()->flash('status', __('Create a system before adding clients.'));
            $this->redirect(route('systems.create', absolute: false), navigate: true);

            return;
        }

        $this->generateApiKey();
    }

    public function generateApiKey(): void
    {
        $this->authorize('clients.manage');

        $this->apiKey = Str::random(40);
    }

    public function save(): void
    {
        $this->authorize('clients.manage');

        $validated = $this->validate([
            'systemId' => ['required', 'integer', 'exists:systems,id'],
            'clientTypeId' => ['nullable', 'integer', 'exists:client_types,id'],
            'addCommandSet' => ['boolean'],
            'name' => ['required', 'string', 'max:255'],
            'apiKey' => ['required', 'string', 'max:255', 'unique:clients,api_key'],
            'widthPx' => ['required', 'integer', 'min:1'],
            'heightPx' => ['required', 'integer', 'min:1'],
            'canScreenshot' => ['boolean'],
            'isActive' => ['boolean'],
        ], [
            'systemId.required' => __('Please select a system.'),
            'systemId.exists' => __('Please select a valid system.'),
            'apiKey.unique' => __('This API key is already in use.'),
        ]);

        $client = Client::query()->create([
            'system_id' => $validated['systemId'],
            'client_type_id' => $validated['clientTypeId'],
            'name' => $validated['name'],
            'api_key' => $validated['apiKey'],
            'width_px' => $validated['widthPx'],
            'height_px' => $validated['heightPx'],
            'can_screenshot' => $validated['canScreenshot'],
            'is_active' => $validated['isActive'],
        ]);

        if ($validated['addCommandSet'] === true && is_int($validated['clientTypeId'])) {
            $clientType = ClientType::query()
                ->with('commands')
                ->findOrFail($validated['clientTypeId']);

            $client->commands()->syncWithoutDetaching($clientType->commands->modelKeys());
        }

        session()->flash('status', __('Client created.'));

        $this->redirect(route('clients.index', absolute: false), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.clients.create');
    }
}

<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Models\Command;
use App\Models\System;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Edit extends Component
{
    use AuthorizesRequests;

    public int $clientId;

    public int $systemId;

    /**
     * @var array<int, int|string>
     */
    public array $supportedCommandIds = [];

    public string $name = '';

    public string $apiKey = '';

    public int $widthPx = 1920;

    public int $heightPx = 1080;

    public bool $canScreenshot = false;

    public ?int $monitorCount = null;

    public bool $isActive = true;

    /**
     * @var Collection<int, System>
     */
    public Collection $systems;

    /**
     * @var Collection<int, Command>
     */
    public Collection $commands;

    public function mount(Client $client): void
    {
        $this->authorize('clients.manage');

        $this->clientId = $client->id;
        $this->systemId = $client->system_id;
        $this->name = $client->name;
        $this->apiKey = $client->api_key;
        $this->widthPx = $client->width_px;
        $this->heightPx = $client->height_px;
        $this->canScreenshot = $client->can_screenshot;
        $this->monitorCount = $client->monitor_count;
        $this->isActive = $client->is_active;

        $this->systems = System::query()->orderBy('name')->get();
        $this->commands = Command::query()->orderBy('name')->get();
        $this->supportedCommandIds = $client->commands()->pluck('commands.id')->all();
    }

    public function generateApiKey(): void
    {
        $this->authorize('clients.manage');

        $this->apiKey = Str::random(40);
    }

    public function updatedCanScreenshot(bool $canScreenshot): void
    {
        if ($canScreenshot === false) {
            $this->monitorCount = null;
        }
    }

    public function save(): void
    {
        $this->authorize('clients.manage');

        $validated = $this->validate([
            'systemId' => ['required', 'integer', 'exists:systems,id'],
            'supportedCommandIds' => ['array'],
            'supportedCommandIds.*' => ['integer', 'exists:commands,id'],
            'name' => ['required', 'string', 'max:255'],
            'apiKey' => [
                'required',
                'string',
                'max:255',
                Rule::unique('clients', 'api_key')->ignore($this->clientId),
            ],
            'widthPx' => ['required', 'integer', 'min:1'],
            'heightPx' => ['required', 'integer', 'min:1'],
            'canScreenshot' => ['boolean'],
            'monitorCount' => ['nullable', 'integer', 'min:1', 'max:10'],
            'isActive' => ['boolean'],
        ], [
            'apiKey.unique' => __('This API key is already in use.'),
        ]);

        $client = Client::query()->findOrFail($this->clientId);

        $client->update([
            'system_id' => $validated['systemId'],
            'name' => $validated['name'],
            'api_key' => $validated['apiKey'],
            'width_px' => $validated['widthPx'],
            'height_px' => $validated['heightPx'],
            'can_screenshot' => $validated['canScreenshot'],
            'monitor_count' => $validated['canScreenshot'] ? ($validated['monitorCount'] ?? null) : null,
            'is_active' => $validated['isActive'],
        ]);

        $client->commands()->sync($validated['supportedCommandIds'] ?? []);

        session()->flash('status', __('Client updated.'));

        $this->redirect(route('clients.index', absolute: false), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.clients.edit');
    }
}

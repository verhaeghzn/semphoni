<?php

namespace App\Livewire\Systems;

use App\Enums\ActionType;
use App\Models\Client;
use App\Models\ClientLog;
use App\Models\Command;
use App\Models\System;
use App\Services\ClientCommandService;
use App\Services\SystemControlLockService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Show extends Component
{
    public int $systemId;

    public string $tab = 'command-center';

    public ?int $clientId = null;

    public ?int $commandId = null;

    public bool $showHeartbeats = false;

    public ?string $lastCommandCorrelationId = null;

    public bool $canControl = false;

    /**
     * Pretty printed JSON for display.
     */
    public ?string $lastResponseJson = null;

    /**
     * @var Collection<int, Client>
     */
    public Collection $clients;

    /**
     * @var Collection<int, Command>
     */
    public Collection $commands;

    public function mount(System $system): void
    {
        $this->systemId = $system->id;

        $userId = Auth::id();

        if (is_int($userId)) {
            $this->canControl = app(SystemControlLockService::class)->attemptAcquireOrRefresh(
                systemId: $this->systemId,
                userId: $userId,
            );
        }

        $this->clients = $system->clients()
            ->with(['latestLog', 'latestNonHeartbeatLog'])
            ->orderBy('name')
            ->get();

        $this->clientId = $this->clients->first()?->id;

        $this->refreshCommands();
    }

    public function updatedClientId(): void
    {
        $this->commandId = null;
        $this->lastCommandCorrelationId = null;
        $this->lastResponseJson = null;

        $this->refreshCommands();
    }

    public function selectTab(string $tab): void
    {
        $this->tab = $tab;
    }

    public function dispatchSelectedCommand(ClientCommandService $service): void
    {
        $this->ensureUserCanControl($this->systemId);

        $client = $this->selectedClient();
        $command = $this->selectedCommand();

        abort_unless(
            Auth::user()?->can($command->permissionName()) === true,
            403
        );

        $this->lastResponseJson = null;
        $this->lastCommandCorrelationId = $service->dispatchToClient($client, $command);

        session()->flash('status', __('Command dispatched.'));
    }

    public function keepControlLockAlive(SystemControlLockService $locks): void
    {
        $userId = Auth::id();

        if (! is_int($userId)) {
            return;
        }

        $this->canControl = $locks->attemptAcquireOrRefresh(
            systemId: $this->systemId,
            userId: $userId,
        );
    }

    public function refreshLockStatus(): void
    {
        $userId = Auth::id();

        if (! is_int($userId)) {
            return;
        }

        $this->canControl = app(SystemControlLockService::class)->attemptAcquireOrRefresh(
            systemId: $this->systemId,
            userId: $userId,
        );
    }

    public function releaseControlLock(SystemControlLockService $locks): void
    {
        $userId = Auth::id();

        abort_unless(is_int($userId), 403);

        $released = $locks->release(
            systemId: $this->systemId,
            userId: $userId,
        );

        abort_unless($released, 403);

        $this->canControl = false;

        session()->flash('status', __('Control released.'));

        $this->redirectRoute('dashboard', navigate: true);
    }

    public function refreshLatestResponse(): void
    {
        if ($this->lastCommandCorrelationId === null || $this->clientId === null) {
            return;
        }

        $log = ClientLog::query()
            ->where('client_id', $this->clientId)
            ->where('payload->event', 'client-command-result')
            ->where('payload->data->correlation_id', $this->lastCommandCorrelationId)
            ->latest('id')
            ->first();

        if (! $log instanceof ClientLog) {
            return;
        }

        $json = json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $this->lastResponseJson = is_string($json) ? $json : null;
    }

    public function render(): View
    {
        $system = System::query()
            ->with('controlLockedBy')
            ->findOrFail($this->systemId);

        $selectedClient = null;
        $selectedClientIsOffline = false;
        $selectedClientOfflineSince = null;

        if (is_int($this->clientId)) {
            $selectedClient = Client::query()
                ->where('system_id', $this->systemId)
                ->with('latestLog')
                ->find($this->clientId);

            if ($selectedClient instanceof Client) {
                $selectedClientIsOffline = ! $selectedClient->isActive();
                $selectedClientOfflineSince = $selectedClient->latestLog?->created_at;
            }
        }

        $logs = ClientLog::query()
            ->with(['client.system', 'command'])
            ->where('system_id', $this->systemId)
            ->when(! $this->showHeartbeats, function (Builder $query): void {
                $query->where(function (Builder $logQuery): void {
                    $logQuery
                        ->whereDoesntHave('command', function (Builder $commandQuery): void {
                            $commandQuery->where('action_type', ActionType::Heartbeat);
                        })
                        ->where('summary', '!=', 'Heartbeat');
                });
            })
            ->latest('id')
            ->limit(200)
            ->get();

        return view('livewire.systems.show', [
            'system' => $system,
            'logs' => $logs,
            'selectedClient' => $selectedClient,
            'selectedClientIsOffline' => $selectedClientIsOffline,
            'selectedClientOfflineSince' => $selectedClientOfflineSince,
        ]);
    }

    private function refreshCommands(): void
    {
        if ($this->clientId === null) {
            $this->commands = collect();

            return;
        }

        $client = Client::query()
            ->with('commands')
            ->find($this->clientId);

        $this->commands = $client?->commands?->sortBy('name')->values() ?? collect();

        $this->commandId = $this->commands->first()?->id;
    }

    private function selectedClient(): Client
    {
        $clientId = $this->clientId;

        abort_unless(is_int($clientId), 404);

        $client = Client::query()
            ->where('system_id', $this->systemId)
            ->findOrFail($clientId);

        return $client;
    }

    private function selectedCommand(): Command
    {
        $commandId = $this->commandId;

        abort_unless(is_int($commandId), 404);

        return Command::query()->findOrFail($commandId);
    }

    private function ensureUserCanControl(int $systemId): void
    {
        $userId = Auth::id();

        abort_unless(is_int($userId), 403);

        $this->canControl = app(SystemControlLockService::class)->attemptAcquireOrRefresh(
            systemId: $systemId,
            userId: $userId,
        );

        abort_unless($this->canControl, 403);
    }
}

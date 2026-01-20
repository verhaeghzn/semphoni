<?php

namespace App\Livewire\Logs;

use App\Enums\ActionType;
use App\Models\Client;
use App\Models\ClientLog;
use App\Models\System;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Component;

class Index extends Component
{
    public ?int $systemId = null;

    public ?int $clientId = null;

    public bool $showHeartbeats = false;

    /**
     * @var Collection<int, System>
     */
    public Collection $systems;

    /**
     * @var Collection<int, Client>
     */
    public Collection $clients;

    public function mount(): void
    {
        $this->systems = System::query()->orderBy('name')->get();
        $this->clients = Client::query()->with('system')->orderBy('name')->get();
    }

    public function updatedSystemId(): void
    {
        if ($this->clientId === null) {
            return;
        }

        $clientSystemId = $this->clients->firstWhere('id', $this->clientId)?->system_id;

        if ($this->systemId !== null && $clientSystemId !== $this->systemId) {
            $this->clientId = null;
        }
    }

    public function render(): View
    {
        $logs = ClientLog::query()
            ->with(['client.system', 'command'])
            ->when(! $this->showHeartbeats, function (Builder $query): void {
                $query->where(function (Builder $logQuery): void {
                    $logQuery
                        ->whereDoesntHave('command', function (Builder $commandQuery): void {
                            $commandQuery->where('action_type', ActionType::Heartbeat);
                        })
                        ->where('summary', '!=', 'Heartbeat');
                });
            })
            ->when($this->systemId !== null, function (Builder $query): void {
                $query->whereHas('client', function (Builder $clientQuery): void {
                    $clientQuery->where('system_id', $this->systemId);
                });
            })
            ->when($this->clientId !== null, function (Builder $query): void {
                $query->where('client_id', $this->clientId);
            })
            ->latest('id')
            ->limit(500)
            ->get();

        return view('livewire.logs.index', [
            'items' => $this->toLogItems($logs)->take(200)->values(),
        ]);
    }

    /**
     * @param  Collection<int, ClientLog>  $logs
     * @return Collection<int, array<string, mixed>>
     */
    private function toLogItems(Collection $logs): Collection
    {
        /** @var array<string, array<string, mixed>> $byCorrelation */
        $byCorrelation = [];

        /** @var list<array<string, mixed>> $standalone */
        $standalone = [];

        foreach ($logs as $log) {
            $correlationId = data_get($log->payload, 'data.correlation_id');

            if (! is_string($correlationId) || $correlationId === '') {
                $standalone[] = [
                    'type' => 'log',
                    'id' => $log->id,
                    'log' => $log,
                    'sort_at' => $log->created_at,
                ];

                continue;
            }

            $key = $log->client_id.'|'.$correlationId;

            if (! isset($byCorrelation[$key])) {
                $byCorrelation[$key] = [
                    'type' => 'correlated',
                    'key' => $key,
                    'correlation_id' => $correlationId,
                    'client' => $log->client,
                    'command' => $log->command,
                    'outbound_log' => null,
                    'inbound_log' => null,
                    'sort_at' => $log->created_at,
                ];
            }

            $event = data_get($log->payload, 'event');

            if ($log->direction->value === 'outbound' && $event === 'server-command') {
                $byCorrelation[$key]['outbound_log'] = $log;
            }

            if ($log->direction->value === 'inbound' && $event === 'client-command-result') {
                $byCorrelation[$key]['inbound_log'] = $log;
            }

            if ($log->created_at->greaterThan($byCorrelation[$key]['sort_at'])) {
                $byCorrelation[$key]['sort_at'] = $log->created_at;
            }

            if ($byCorrelation[$key]['command'] === null && $log->command !== null) {
                $byCorrelation[$key]['command'] = $log->command;
            }
        }

        $items = collect()
            ->concat(array_values($byCorrelation))
            ->concat($standalone)
            ->sortByDesc('sort_at')
            ->values();

        return $items;
    }
}

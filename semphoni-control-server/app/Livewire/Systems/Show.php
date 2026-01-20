<?php

namespace App\Livewire\Systems;

use App\Enums\ActionType;
use App\Models\Client;
use App\Models\ClientLog;
use App\Models\ClientScreenshot;
use App\Models\Command;
use App\Models\System;
use App\Services\ClientCommandService;
use App\Services\SystemControlLockService;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Spatie\Permission\Models\Permission;

class Show extends Component
{
    public int $systemId;

    public string $tab = 'command-center';

    public ?int $clientId = null;

    public ?int $commandId = null;

    public bool $showHeartbeats = false;

    public bool $visualFeedEnabled = false;

    public bool $visualFeedFullscreen = false;

    public int $visualFeedIntervalSeconds = 5;

    public int $visualFeedMonitorNr = 1;

    public ?string $lastCommandCorrelationId = null;

    public ?string $lastScreenshotCorrelationId = null;

    public bool $canControl = false;

    /**
     * Pretty printed JSON for display.
     */
    public ?string $lastResponseJson = null;

    public ?string $screenshotDataUrl = null;

    public ?string $lastScreenshotTakenAtIso = null;

    public ?string $lastScreenshotTakenAtLabel = null;

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
        $this->loadSavedScreenshot();
    }

    public function updatedClientId(): void
    {
        $this->commandId = null;
        $this->lastCommandCorrelationId = null;
        $this->lastScreenshotCorrelationId = null;
        $this->lastResponseJson = null;
        $this->visualFeedEnabled = false;
        $this->visualFeedFullscreen = false;
        $this->screenshotDataUrl = null;
        $this->lastScreenshotTakenAtIso = null;
        $this->lastScreenshotTakenAtLabel = null;

        $this->refreshCommands();
        $this->loadSavedScreenshot();
    }

    public function selectTab(string $tab): void
    {
        $this->tab = $tab;

        if ($tab !== 'command-center') {
            $this->visualFeedFullscreen = false;
        }
    }

    public function toggleVisualFeedFullscreen(): void
    {
        $this->visualFeedFullscreen = ! $this->visualFeedFullscreen;
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

    public function updatedVisualFeedEnabled(bool $enabled): void
    {
        if ($enabled === false) {
            return;
        }

        $this->ensureUserCanControl($this->systemId);

        $this->requestScreenshot();
    }

    public function updatedVisualFeedIntervalSeconds(mixed $seconds): void
    {
        $this->visualFeedIntervalSeconds = max(1, min(60, (int) $seconds));
    }

    public function updatedVisualFeedMonitorNr(mixed $monitorNr): void
    {
        $this->visualFeedMonitorNr = max(1, min(16, (int) $monitorNr));
    }

    public function refreshScreenshot(ClientCommandService $service): void
    {
        if (! $this->visualFeedEnabled) {
            return;
        }

        $this->ensureUserCanControl($this->systemId);

        $updated = $this->loadLatestScreenshotFromLogs();

        if (! $updated) {
            return;
        }

        $this->requestScreenshot($service);
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

    private function requestScreenshot(?ClientCommandService $service = null): void
    {
        $this->ensureUserCanControl($this->systemId);

        $client = $this->selectedClient();

        abort_unless($client->can_screenshot === true, 403);

        $command = Command::query()->firstOrCreate(
            ['name' => 'get_screenshot'],
            [
                'action_type' => ActionType::ButtonPress,
                'description' => 'Fetch a screenshot from the client.',
            ]
        );

        $permissionExists = Permission::query()
            ->where('name', $command->permissionName())
            ->exists();

        if ($permissionExists) {
            abort_unless(
                Auth::user()?->can($command->permissionName()) === true,
                403
            );
        }

        $service ??= app(ClientCommandService::class);

        $this->lastScreenshotCorrelationId = $service->dispatchToClient($client, $command, [
            'monitor_nr' => $this->visualFeedMonitorNr,
        ]);
    }

    private function loadLatestScreenshotFromLogs(): bool
    {
        if ($this->lastScreenshotCorrelationId === null || $this->clientId === null) {
            return false;
        }

        $log = ClientLog::query()
            ->where('client_id', $this->clientId)
            ->where('payload->event', 'client-command-result')
            ->where(function (Builder $query): void {
                $query
                    ->where('payload->data->correlation_id', $this->lastScreenshotCorrelationId)
                    ->orWhere('payload->data->correlationId', $this->lastScreenshotCorrelationId);
            })
            ->latest('id')
            ->first();

        if (! $log instanceof ClientLog) {
            return false;
        }

        $payload = is_array($log->payload) ? $log->payload : null;
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : null;
        $resultPayload = is_array($data['payload'] ?? null)
            ? $data['payload']
            : (is_array($data) ? $data : null);

        if (! is_array($resultPayload)) {
            return false;
        }

        $mime = $resultPayload['mime']
            ?? $resultPayload['content_type']
            ?? $resultPayload['contentType']
            ?? null;

        $encoding = $resultPayload['encoding'] ?? null;
        $imageBase64 = $resultPayload['jpeg_base64']
            ?? $resultPayload['jpg_base64']
            ?? $resultPayload['image_base64']
            ?? $resultPayload['base64']
            ?? null;

        $allowedMimes = ['image/jpeg', 'image/jpg'];

        if (! is_string($imageBase64) || $imageBase64 === '') {
            return false;
        }

        if (! is_string($encoding) || $encoding === '') {
            $encoding = 'base64';
        }

        $keyImpliesJpeg = isset($resultPayload['jpeg_base64']) || isset($resultPayload['jpg_base64']);

        if (! is_string($mime) || $mime === '') {
            if (! $keyImpliesJpeg) {
                return false;
            }

            $mime = 'image/jpeg';
        }

        if (! in_array($mime, $allowedMimes, true) || $encoding !== 'base64') {
            return false;
        }

        $mime = $mime === 'image/jpg' ? 'image/jpeg' : $mime;

        $this->screenshotDataUrl = 'data:'.$mime.';base64,'.$imageBase64;
        $this->setLastScreenshotTimestamp($log->created_at);

        $client = Client::query()
            ->where('system_id', $this->systemId)
            ->find($this->clientId);

        if (! $client instanceof Client) {
            return false;
        }

        ClientScreenshot::query()->updateOrCreate(
            ['client_id' => $client->id],
            [
                'mime' => $mime,
                'base64' => $imageBase64,
                'taken_at' => $log->created_at,
            ],
        );

        return true;
    }

    private function loadSavedScreenshot(): void
    {
        if ($this->clientId === null) {
            return;
        }

        $client = Client::query()
            ->where('system_id', $this->systemId)
            ->with('screenshot')
            ->find($this->clientId);

        if (! $client instanceof Client) {
            return;
        }

        $screenshot = $client->screenshot;

        $mime = $screenshot?->mime;
        $imageBase64 = $screenshot?->base64;

        if (! is_string($imageBase64) || $imageBase64 === '') {
            $this->screenshotDataUrl = null;
            $this->setLastScreenshotTimestamp($screenshot?->taken_at);

            return;
        }

        if (
            ! is_string($mime)
            || $mime === ''
            || ! in_array($mime, ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'], true)
        ) {
            $this->screenshotDataUrl = null;
            $this->setLastScreenshotTimestamp($screenshot?->taken_at);

            return;
        }

        $mime = $mime === 'image/jpg' ? 'image/jpeg' : $mime;

        $this->screenshotDataUrl = 'data:'.$mime.';base64,'.$imageBase64;

        $this->setLastScreenshotTimestamp($screenshot?->taken_at);
    }

    private function setLastScreenshotTimestamp(?CarbonInterface $takenAt): void
    {
        if (! $takenAt instanceof CarbonInterface) {
            $this->lastScreenshotTakenAtIso = null;
            $this->lastScreenshotTakenAtLabel = null;

            return;
        }

        $this->lastScreenshotTakenAtIso = $takenAt->toIso8601String();
        $this->lastScreenshotTakenAtLabel = $takenAt->toDayDateTimeString();
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

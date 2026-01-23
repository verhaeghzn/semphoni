<?php

namespace App\Livewire\Systems;

use App\Enums\ActionType;
use App\Models\Client;
use App\Models\ClientLog;
use App\Models\ClientScreenshot;
use App\Models\Command;
use App\Services\ClientCommandService;
use App\Services\SystemControlLockService;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Spatie\Permission\Models\Permission;

class ClientVisualFeed extends Component
{
    public int $systemId;

    public int $clientId;

    public bool $canControl = false;

    public bool $hideTitle = false;

    public string $clientName = '';

    public bool $clientIsOffline = false;

    public bool $visualFeedEnabled = false;

    public bool $visualFeedFullscreen = false;

    public int $visualFeedIntervalSeconds = 5;

    public int $visualFeedMonitorNr = 1;

    public int $visualFeedMonitorMax = 3;

    public ?string $lastScreenshotCorrelationId = null;

    public ?string $screenshotDataUrl = null;

    public ?string $lastScreenshotTakenAtIso = null;

    public ?string $lastScreenshotTakenAtLabel = null;

    public function mount(int $systemId, int $clientId, bool $canControl, bool $hideTitle = false): void
    {
        $this->systemId = $systemId;
        $this->clientId = $clientId;
        $this->canControl = $canControl;
        $this->hideTitle = $hideTitle;

        $this->refreshClientState();
        $this->loadVisualFeedSettings();
        $this->loadSavedScreenshot();
    }

    public function updatedVisualFeedEnabled(bool $enabled): void
    {
        if ($enabled === false) {
            return;
        }

        $this->refreshClientState();

        if (! $this->canControl || $this->clientIsOffline) {
            $this->visualFeedEnabled = false;

            return;
        }

        $this->requestScreenshot();
    }

    public function updatedVisualFeedIntervalSeconds(mixed $seconds): void
    {
        $this->visualFeedIntervalSeconds = max(1, min(60, (int) $seconds));
        $this->persistVisualFeedSettings();
    }

    public function updatedVisualFeedMonitorNr(mixed $monitorNr): void
    {
        $this->visualFeedMonitorNr = max(1, min($this->visualFeedMonitorMax, (int) $monitorNr));
        $this->persistVisualFeedSettings();
        $this->loadSavedScreenshot();
    }

    public function decrementVisualFeedInterval(): void
    {
        $this->visualFeedIntervalSeconds = max(1, $this->visualFeedIntervalSeconds - 1);
        $this->persistVisualFeedSettings();
    }

    public function incrementVisualFeedInterval(): void
    {
        $this->visualFeedIntervalSeconds = min(60, $this->visualFeedIntervalSeconds + 1);
        $this->persistVisualFeedSettings();
    }

    public function refreshScreenshot(ClientCommandService $service): void
    {
        if (! $this->visualFeedEnabled) {
            return;
        }

        $this->refreshClientState();

        if (! $this->canControl || $this->clientIsOffline) {
            $this->visualFeedEnabled = false;

            return;
        }

        $updated = $this->loadLatestScreenshotFromLogs();

        if (! $updated) {
            return;
        }

        $this->requestScreenshot($service);
    }

    public function toggleVisualFeedFullscreen(): void
    {
        $this->visualFeedFullscreen = ! $this->visualFeedFullscreen;
    }

    public function render(): View
    {
        return view('livewire.systems.client-visual-feed');
    }

    private function refreshClientState(): void
    {
        $client = $this->client();

        if (! $client instanceof Client) {
            $this->clientName = __('Client');
            $this->clientIsOffline = true;
            $this->visualFeedMonitorMax = 3;

            return;
        }

        $this->clientName = $client->name;
        $this->clientIsOffline = ! $client->isActive();

        $this->visualFeedMonitorMax = $this->normalizeMonitorCount($client->monitor_count);

        $previousMonitor = $this->visualFeedMonitorNr;
        $this->visualFeedMonitorNr = max(1, min($this->visualFeedMonitorMax, $this->visualFeedMonitorNr));

        if ($this->visualFeedMonitorNr !== $previousMonitor) {
            $this->persistVisualFeedSettings();
        }
    }

    private function client(): ?Client
    {
        return Client::query()
            ->where('system_id', $this->systemId)
            ->with(['latestLog'])
            ->find($this->clientId);
    }

    private function requestScreenshot(?ClientCommandService $service = null): void
    {
        $this->ensureUserCanControl($this->systemId);

        $client = $this->client();

        if (! $client instanceof Client) {
            return;
        }

        abort_unless($client->can_screenshot === true, 403);
        abort_unless($client->is_active === true, 403);

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

    private function normalizeMonitorCount(?int $monitorCount): int
    {
        $monitorCount ??= 3;

        return max(1, min(10, $monitorCount));
    }

    private function loadVisualFeedSettings(): void
    {
        $settings = session()->get($this->visualFeedSettingsKey());

        if (! is_array($settings)) {
            return;
        }

        $interval = $settings['interval_seconds'] ?? null;
        $monitor = $settings['monitor_nr'] ?? null;

        if (is_int($interval) || is_string($interval)) {
            $this->visualFeedIntervalSeconds = max(1, min(60, (int) $interval));
        }

        if (is_int($monitor) || is_string($monitor)) {
            $this->visualFeedMonitorNr = max(1, min($this->visualFeedMonitorMax, (int) $monitor));
        }
    }

    private function persistVisualFeedSettings(): void
    {
        session()->put($this->visualFeedSettingsKey(), [
            'interval_seconds' => $this->visualFeedIntervalSeconds,
            'monitor_nr' => $this->visualFeedMonitorNr,
        ]);
    }

    private function visualFeedSettingsKey(): string
    {
        $userId = Auth::id() ?? 0;

        return 'visual_feed.settings.user_'.$userId.'.client_'.$this->clientId;
    }

    private function loadLatestScreenshotFromLogs(): bool
    {
        if ($this->lastScreenshotCorrelationId === null) {
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
        $this->loadSavedScreenshot();

        return true;
    }

    private function loadSavedScreenshot(): void
    {
        $screenshot = ClientScreenshot::query()
            ->where('client_id', $this->clientId)
            ->where('monitor_nr', $this->visualFeedMonitorNr)
            ->first();

        $takenAt = $screenshot?->taken_at;
        $this->setLastScreenshotTimestamp($takenAt);

        $storagePath = $screenshot?->storage_path;

        if (! is_string($storagePath) || $storagePath === '') {
            $this->screenshotDataUrl = null;

            return;
        }

        $v = $takenAt instanceof CarbonInterface ? $takenAt->timestamp : time();

        $this->screenshotDataUrl = route('systems.clients.visual-feed.latest', [
            'system' => $this->systemId,
            'client' => $this->clientId,
            'monitorNr' => $this->visualFeedMonitorNr,
            'v' => $v,
        ], absolute: false);
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

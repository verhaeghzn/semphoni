<?php

namespace App\Providers;

use App\Enums\LogDirection;
use App\Enums\LogSeverity;
use App\Models\Client;
use App\Models\ClientLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use React\EventLoop\Loop;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureReverbClientLivenessChecker();
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }

    protected function configureAuthorization(): void
    {
        Gate::define('systems.manage', fn (User $user): bool => $user->hasRole('Admin'));
        Gate::define('clients.manage', fn (User $user): bool => $user->hasRole('Admin'));
        Gate::define('users.manage', fn (User $user): bool => $user->hasRole('Admin'));
    }

    protected function configureReverbClientLivenessChecker(): void
    {
        if (! app()->runningInConsole()) {
            return;
        }

        $argv = $_SERVER['argv'] ?? null;

        if (! is_array($argv) || ! in_array('reverb:start', $argv, true)) {
            return;
        }

        Loop::get()->addPeriodicTimer(10, function (): void {
            $clients = Client::query()
                ->where('is_active', true)
                ->with('latestLog')
                ->get();

            foreach ($clients as $client) {
                $isAlive = $client->isActive();
                $cacheKey = 'clients:liveness:'.$client->id;

                $previous = Cache::get($cacheKey);

                if (! is_bool($previous)) {
                    Cache::put($cacheKey, $isAlive, now()->addDay());

                    continue;
                }

                if ($previous === $isAlive) {
                    continue;
                }

                Cache::put($cacheKey, $isAlive, now()->addDay());

                ClientLog::query()->create([
                    'client_id' => $client->id,
                    'system_id' => $client->system_id,
                    'direction' => LogDirection::Outbound,
                    'severity' => $isAlive ? LogSeverity::Info : LogSeverity::Error,
                    'command_id' => null,
                    'summary' => $isAlive ? 'Client is online' : 'Client is offline',
                    'payload' => [
                        'type' => 'liveness',
                        'is_alive' => $isAlive,
                    ],
                ]);
            }
        });
    }
}

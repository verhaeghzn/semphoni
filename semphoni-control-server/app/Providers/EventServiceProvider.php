<?php

namespace App\Providers;

use App\Listeners\HandleReverbMessageReceived;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Laravel\Reverb\Events\MessageReceived;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, list<class-string>>
     */
    protected $listen = [
        MessageReceived::class => [
            HandleReverbMessageReceived::class,
        ],
    ];
}


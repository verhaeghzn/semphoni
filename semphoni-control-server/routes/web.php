<?php

use App\Livewire\Clients\Create as ClientsCreate;
use App\Livewire\Clients\Edit as ClientsEdit;
use App\Livewire\Clients\Index as ClientsIndex;
use App\Livewire\Commands\Edit as CommandsEdit;
use App\Livewire\Commands\Index as CommandsIndex;
use App\Livewire\Logs\Index as LogsIndex;
use App\Livewire\Systems\Create as SystemsCreate;
use App\Livewire\Systems\Edit as SystemsEdit;
use App\Livewire\Systems\Index as SystemsIndex;
use App\Http\Controllers\ClientReverbAuthController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified', '2fa'])
    ->name('dashboard');

Route::post('client/broadcasting/auth', ClientReverbAuthController::class)
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('client.broadcasting.auth');

Route::middleware(['auth', 'verified', '2fa'])->group(function () {
    Route::livewire('systems', SystemsIndex::class)->name('systems.index');
    Route::livewire('systems/create', SystemsCreate::class)->middleware('can:systems.manage')->name('systems.create');
    Route::livewire('systems/{system}', \App\Livewire\Systems\Show::class)->name('systems.show');
    Route::livewire('systems/{system}/edit', SystemsEdit::class)->middleware('can:systems.manage')->name('systems.edit');

    Route::livewire('clients', ClientsIndex::class)->name('clients.index');
    Route::livewire('clients/create', ClientsCreate::class)->middleware('can:clients.manage')->name('clients.create');
    Route::livewire('clients/{client}/edit', ClientsEdit::class)->middleware('can:clients.manage')->name('clients.edit');

    Route::livewire('commands', CommandsIndex::class)->name('commands.index');
    Route::livewire('commands/{command}/edit', CommandsEdit::class)->name('commands.edit');

    Route::livewire('logs', LogsIndex::class)->name('logs.index');
});

require __DIR__.'/settings.php';

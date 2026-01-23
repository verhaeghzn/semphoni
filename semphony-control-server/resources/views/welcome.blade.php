<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white text-zinc-900 antialiased">
        <div class="relative isolate">
            <div class="absolute inset-0 -z-10 overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-b from-zinc-50 to-white"></div>
                <x-placeholder-pattern
                    class="absolute inset-0 stroke-zinc-200/70 [mask-image:radial-gradient(60%_60%_at_50%_30%,black,transparent)]"
                />
            </div>

            <div class="mx-auto max-w-6xl px-6 py-16 sm:py-24">
                <div class="flex flex-col gap-12 lg:flex-row lg:items-start lg:justify-between">
                    <div class="max-w-2xl">
                        <div class="flex items-center gap-4">
                            <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }} logo" class="h-16 w-auto" />

                            <div class="min-w-0">
                                <p class="text-sm font-medium text-zinc-500">Control server</p>
                                <h1 class="mt-1 text-3xl font-semibold tracking-tight text-zinc-900 sm:text-4xl">
                                    {{ config('app.name') }}
                                </h1>
                            </div>
                        </div>

                        <p class="mt-6 text-base leading-relaxed text-zinc-600">
                            Manage connected systems and clients, dispatch commands, and inspect logs — all from one place.
                        </p>

                        <div class="mt-8 grid gap-4 sm:grid-cols-2">
                            <div class="rounded-2xl border border-zinc-200 bg-white/70 p-5 shadow-xs backdrop-blur">
                                <p class="text-sm font-semibold text-zinc-900">Systems</p>
                                <p class="mt-1 text-sm text-zinc-600">Register systems and see what’s online.</p>
                            </div>

                            <div class="rounded-2xl border border-zinc-200 bg-white/70 p-5 shadow-xs backdrop-blur">
                                <p class="text-sm font-semibold text-zinc-900">Clients</p>
                                <p class="mt-1 text-sm text-zinc-600">Track active clients and their configuration.</p>
                            </div>

                            <div class="rounded-2xl border border-zinc-200 bg-white/70 p-5 shadow-xs backdrop-blur">
                                <p class="text-sm font-semibold text-zinc-900">Commands</p>
                                <p class="mt-1 text-sm text-zinc-600">Dispatch commands and confirm delivery.</p>
                            </div>

                            <div class="rounded-2xl border border-zinc-200 bg-white/70 p-5 shadow-xs backdrop-blur">
                                <p class="text-sm font-semibold text-zinc-900">Logs</p>
                                <p class="mt-1 text-sm text-zinc-600">Inspect correlated logs across the platform.</p>
                            </div>
                        </div>

                        <p class="mt-6 text-sm text-zinc-500">
                            Dashboard access requires authentication, email verification, and 2FA.
                        </p>

                        <div class="mt-8 flex flex-wrap items-center gap-3">
                            @auth
                                <flux:button variant="primary" :href="route('dashboard')" wire:navigate>
                                    Go to dashboard
                                </flux:button>

                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <flux:button variant="ghost" as="button" type="submit">
                                        Log out
                                    </flux:button>
                                </form>
                            @else
                                @if (Route::has('login'))
                                    <flux:button variant="primary" :href="route('login')" wire:navigate>
                                        Log in
                                    </flux:button>
                                @endif

                                @if (Route::has('register'))
                                    <flux:button variant="ghost" :href="route('register')" wire:navigate>
                                        Create account
                                    </flux:button>
                                @endif
                            @endauth
                        </div>
                    </div>

                    <div class="w-full max-w-lg">
                        <div class="rounded-2xl border border-zinc-200 bg-white shadow-sm">
                            <div class="p-6">
                                <flux:heading size="lg">Getting started</flux:heading>
                                <flux:text class="mt-2 text-zinc-600">
                                    Sign in to access the dashboard and start managing systems, clients, and command dispatch.
                                </flux:text>

                                <div class="mt-6 grid gap-3">
                                    <a
                                        class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm font-medium text-zinc-900 transition hover:bg-zinc-100"
                                        href="{{ route('dashboard') }}"
                                    >
                                        Dashboard
                                    </a>

                                    <div class="rounded-xl border border-zinc-200 bg-white px-4 py-3 text-sm text-zinc-600">
                                        Tip: if you get blocked by 2FA, finish setup in <span class="font-medium text-zinc-900">Settings</span> after signing in.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @fluxScripts
    </body>
</html>

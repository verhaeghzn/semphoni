<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="lg">{{ __('Clients') }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-600">
                {{ __('Connected microscope clients (Python).') }}
            </flux:text>
        </div>

        @can('clients.manage')
            <flux:button variant="primary" :href="route('clients.create')" wire:navigate>
                {{ __('New client') }}
            </flux:button>
        @endcan
    </div>

    <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white">
        <div class="divide-y divide-neutral-200">
            @forelse ($clients as $client)
                <div class="flex items-start justify-between gap-4 p-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="mt-1 inline-block size-2 rounded-full {{ $client->is_active ? ($client->isActive() ? 'bg-green-500' : 'bg-red-500') : 'bg-zinc-400' }}" aria-hidden="true"></span>
                            <flux:heading>{{ $client->name }}</flux:heading>
                        </div>

                        <flux:text class="mt-1 text-sm text-zinc-600">
                            {{ __('System: :system', ['system' => $client->system?->name ?? '—']) }}
                            · {{ $client->width_px }}×{{ $client->height_px }}
                            · {{ $client->can_screenshot ? __('Can screenshot') : __('No screenshot') }}
                            @if (! $client->is_active)
                                · {{ __('Websocket disabled') }}
                            @endif
                        </flux:text>

                        <flux:text class="mt-1 truncate text-sm text-zinc-600">
                            {{ $client->latestLog?->summary ?? __('No activity yet') }}
                        </flux:text>
                    </div>

                    <div class="flex items-center gap-2">
                        @php
                            $installModalName = 'client-install-'.$client->id;
                            $baseUrl = url('/');
                            $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'http';
                            $wsScheme = $scheme === 'https' ? 'wss' : 'ws';
                            $host = request()->getHost();
                            $port = request()->getPort();
                            $reverbKey = (string) config('broadcasting.connections.reverb.key');
                            $wsUrl = $wsScheme.'://'.$host.(in_array($port, [80, 443], true) ? '' : ':'.$port).'/app/'.$reverbKey;
                            $authEndpoint = url('/client/broadcasting/auth');
                            $channelName = 'presence-client.'.$client->id;
                        @endphp

                        @if (! $client->isActive())
                            <flux:modal.trigger name="{{ $installModalName }}">
                                <flux:button
                                    variant="outline"
                                    size="sm"
                                    x-data=""
                                    x-on:click.prevent="$dispatch('open-modal', '{{ $installModalName }}')"
                                >
                                    {{ __('Install') }}
                                </flux:button>
                            </flux:modal.trigger>
                        @endif

                        @can('clients.manage')
                            <flux:button variant="outline" size="sm" :href="route('clients.edit', $client)" wire:navigate>
                                {{ __('Edit') }}
                            </flux:button>
                        @endcan
                    </div>
                </div>

                @if (! $client->isActive())
                    <flux:modal name="{{ $installModalName }}" class="max-w-3xl">
                        <div class="space-y-6">
                            <div class="space-y-2">
                                <flux:heading size="lg">{{ __('Install client websocket connection') }}</flux:heading>
                                <flux:text class="text-sm text-zinc-600">
                                    {{ __('Use the info below to connect an external client to Reverb, subscribe to its channel, and send heartbeats every 10 seconds.') }}
                                </flux:text>
                            </div>

                            @if (! $client->is_active)
                                <flux:callout
                                    variant="warning"
                                    icon="exclamation-triangle"
                                    heading="{{ __('This client is disabled') }}"
                                >
                                    {{ __('Enable “Websocket + heartbeat checks” for this client first; otherwise auth/subscription will be rejected.') }}
                                </flux:callout>
                            @endif

                            <div class="space-y-2">
                                <flux:heading>{{ __('Credentials') }}</flux:heading>
                                <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-3 font-mono text-xs whitespace-pre-wrap">
Client ID: {{ $client->id }}
Channel: {{ $channelName }}
Auth header: X-Client-Key: {{ $client->api_key }}
WebSocket URL: {{ $wsUrl }}?protocol=7&client=python&version=0.1&flash=false
Auth endpoint (HTTP): {{ $authEndpoint }}
                                </div>
                                <flux:text class="text-sm text-zinc-600">
                                    {{ __('Treat the API key as a secret. It uniquely identifies the client and is required to subscribe to its channel.') }}
                                </flux:text>
                            </div>

                            <div class="space-y-2">
                                <flux:heading>{{ __('How it works') }}</flux:heading>
                                <div class="space-y-2 text-sm text-zinc-700">
                                    <div>
                                        {{ __('We use Reverb’s Pusher-compatible WebSocket protocol.') }}
                                    </div>
                                    <div>
                                        {{ __('Each client subscribes to a presence channel named:') }}
                                        <span class="font-mono">{{ $channelName }}</span>
                                    </div>
                                    <div>
                                        {{ __('The client sends a “client-heartbeat” event every 10 seconds. The server logs heartbeats and checks liveness continuously.') }}
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <flux:heading>{{ __('Step-by-step (copy/paste)') }}</flux:heading>
                                <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-3 font-mono text-xs whitespace-pre-wrap">
1) Open WebSocket:
   {{ $wsUrl }}?protocol=7&client=python&version=0.1&flash=false

2) Wait for "pusher:connection_established" and read the returned socket_id.

3) Call auth endpoint (HTTP POST) with:
   URL: {{ $authEndpoint }}
   Header: X-Client-Key: {{ $client->api_key }}
   JSON:
   {
     "socket_id": "<socket_id_from_step_2>",
     "channel_name": "{{ $channelName }}"
   }
   Response:
   {
     "auth": "<auth_string>",
     "channel_data": "<json_string>"
   }

4) Subscribe via WebSocket:
   {
     "event": "pusher:subscribe",
     "data": {
       "channel": "{{ $channelName }}",
       "auth": "<auth_from_step_3>",
       "channel_data": "<channel_data_from_step_3>"
     }
   }

5) Heartbeat every 10 seconds (client event):
   {
     "event": "client-heartbeat",
     "channel": "{{ $channelName }}",
     "data": { "ts": "<iso8601>", "version": "dev" }
   }
                                </div>
                            </div>

                            <div class="space-y-2">
                                <flux:heading>{{ __('Commands + responses') }}</flux:heading>
                                <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-3 font-mono text-xs whitespace-pre-wrap">
Server -> Client (received on channel "{{ $channelName }}"):
Event: "server-command"
Data example:
{
  "client_id": {{ $client->id }},
  "correlation_id": "<uuid>",
  "command_name": "clickButton",
  "payload": { "button_name": "<button_name>" }
}

Client -> Server response (client event):
{
  "event": "client-command-result",
  "channel": "{{ $channelName }}",
  "data": {
    "correlation_id": "<uuid>",
    "command_name": "clickButton",
    "payload": { "button_name": "<button_name>" },
    "ok": true,
    "message": "done"
  }
}
                                </div>
                            </div>

                            <div class="flex justify-end gap-2">
                                <flux:modal.close>
                                    <flux:button variant="filled">{{ __('Close') }}</flux:button>
                                </flux:modal.close>
                            </div>
                        </div>
                    </flux:modal>
                @endif
            @empty
                <div class="p-6">
                    <flux:text class="text-sm text-zinc-600">
                        {{ __('No clients yet.') }}
                    </flux:text>
                </div>
            @endforelse
        </div>
    </div>
</div>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="lg">{{ __('Client logs') }}</flux:heading>
        <flux:text class="mt-1 text-sm text-zinc-600">
            {{ __('Recent inbound/outbound messages and command activity (latest 200).') }}
        </flux:text>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <flux:select wire:model.live="systemId" :label="__('System (optional)')" placeholder="{{ __('All systems') }}">
            @foreach ($systems as $system)
                <flux:select.option :value="$system->id">{{ $system->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="clientId" :label="__('Client (optional)')" placeholder="{{ __('All clients') }}">
            @foreach ($clients as $client)
                <flux:select.option :value="$client->id">
                    {{ $client->name }} ({{ $client->system?->name ?? '—' }})
                </flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <div>
        <flux:checkbox wire:model.live="showHeartbeats" :label="__('Show heartbeats')" />
    </div>

    <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white">
        <div class="divide-y divide-neutral-200">
            @forelse ($items as $item)
                @if (($item['type'] ?? null) === 'correlated')
                    @php
                        /** @var \App\Models\Client $client */
                        $client = $item['client'];
                        /** @var \App\Models\Command|null $command */
                        $command = $item['command'];
                        /** @var \App\Models\ClientLog|null $outbound */
                        $outbound = $item['outbound_log'];
                        /** @var \App\Models\ClientLog|null $inbound */
                        $inbound = $item['inbound_log'];
                        $correlationId = $item['correlation_id'];

                        $outboundData = $outbound?->payload ?? null;
                        $inboundData = $inbound?->payload ?? null;

                        $outboundJson = $outboundData ? json_encode($outboundData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : null;
                        $inboundJson = $inboundData ? json_encode($inboundData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : null;

                        $ok = data_get($inboundData, 'data.ok');
                        $message = data_get($inboundData, 'data.message');
                        $commandName = $command?->name ?? data_get($outboundData, 'data.payload.button_name') ?? data_get($outboundData, 'data.command_name') ?? '—';
                    @endphp

                    <details class="p-4">
                        <summary class="cursor-pointer list-none">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <flux:badge>{{ __('command') }}</flux:badge>
                                    <flux:text class="text-sm text-zinc-600">
                                        {{ $client->system?->name ?? '—' }} / {{ $client->name }}
                                    </flux:text>
                                    <flux:badge color="zinc">{{ $commandName }}</flux:badge>

                                    @if (is_bool($ok))
                                        <flux:badge color="{{ $ok ? 'green' : 'red' }}">
                                            {{ $ok ? __('ok') : __('error') }}
                                        </flux:badge>
                                    @else
                                        <flux:badge color="zinc">{{ __('pending') }}</flux:badge>
                                    @endif
                                </div>

                                <flux:text class="text-xs text-zinc-500">
                                    {{ __('ID: :id', ['id' => $correlationId]) }}
                                </flux:text>
                            </div>

                            @if (is_string($message) && $message !== '')
                                <flux:text class="mt-2 text-sm text-zinc-800">
                                    {{ $message }}
                                </flux:text>
                            @endif
                        </summary>

                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <flux:text class="text-sm font-medium text-zinc-800">{{ __('Sent') }}</flux:text>
                                @if (is_string($outboundJson))
                                    <pre class="overflow-auto rounded-lg border border-neutral-200 bg-neutral-50 p-3 text-xs">{{ $outboundJson }}</pre>
                                @else
                                    <flux:text class="text-sm text-zinc-600">{{ __('No outbound payload found.') }}</flux:text>
                                @endif
                            </div>

                            <div class="space-y-2">
                                <flux:text class="text-sm font-medium text-zinc-800">{{ __('Received') }}</flux:text>
                                @if (is_string($inboundJson))
                                    <pre class="overflow-auto rounded-lg border border-neutral-200 bg-neutral-50 p-3 text-xs">{{ $inboundJson }}</pre>
                                @else
                                    <flux:text class="text-sm text-zinc-600">{{ __('No response yet.') }}</flux:text>
                                @endif
                            </div>
                        </div>
                    </details>
                @else
                    @php
                        /** @var \App\Models\ClientLog $log */
                        $log = $item['log'];
                    @endphp

                    <div class="p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:badge>{{ $log->direction->value }}</flux:badge>
                                <flux:text class="text-sm text-zinc-600">
                                    {{ $log->client->system?->name ?? '—' }} / {{ $log->client->name }}
                                </flux:text>
                                @if ($log->command)
                                    <flux:badge color="zinc">{{ $log->command->name }}</flux:badge>
                                @endif
                            </div>

                            <flux:text class="text-xs text-zinc-500">
                                {{ $log->created_at->diffForHumans() }}
                            </flux:text>
                        </div>

                        <flux:text class="mt-2 text-sm text-zinc-800">
                            {{ $log->summary }}
                        </flux:text>
                    </div>
                @endif
            @empty
                <div class="p-6">
                    <flux:text class="text-sm text-zinc-600">
                        {{ __('No logs yet.') }}
                    </flux:text>
                </div>
            @endforelse
        </div>
    </div>
</div>

@extends('nexus::layouts.app')

@section('title', t('messages.nexus.developer.webhooks.title'))

@section('content')
    <div class="mx-auto max-w-3xl space-y-4">
        @if ($plainSecret)
            <x-nexus-panel style="border-color: var(--color-nexus-success)">
                <p class="mb-2 text-sm font-semibold text-nexus-success">{{ t('messages.nexus.developer.webhooks.reveal_title') }}</p>
                <p class="mb-2 text-xs text-nexus-text-muted">{{ t('messages.nexus.developer.webhooks.reveal_warning') }}</p>
                <code class="block break-all rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text">{{ $plainSecret }}</code>
            </x-nexus-panel>
        @endif

        <x-nexus-panel :title="t('messages.nexus.developer.webhooks.title')">
            <p class="mb-4 text-sm text-nexus-text-muted">{{ t('messages.nexus.developer.webhooks.description') }}</p>

            <form method="POST" action="{{ route('nexus.developer.webhooks.store') }}" class="mb-6 space-y-3 rounded-md border border-nexus-border bg-nexus-surface-1 p-4">
                @csrf
                <div>
                    <label class="mb-1 block text-xs text-nexus-text-muted">{{ t('messages.nexus.developer.webhooks.url') }}</label>
                    <input type="url" name="url" required maxlength="2048" class="block w-full rounded-md border border-nexus-border bg-nexus-surface-2 px-3 py-1.5 text-sm text-nexus-text" placeholder="https://example.com/webhooks/nexus">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-nexus-text-muted">{{ t('messages.nexus.developer.webhooks.events') }}</label>
                    <div class="flex flex-wrap gap-3">
                        @foreach ($events as $event)
                            <label class="flex items-center gap-1.5 text-sm text-nexus-text">
                                <input type="checkbox" name="events[]" value="{{ $event->value }}">
                                {{ t('messages.nexus.developer.webhooks.event.'.$event->value) }}
                            </label>
                        @endforeach
                    </div>
                </div>
                <button type="submit" class="rounded-md bg-nexus-cyan/20 px-4 py-1.5 text-sm font-semibold text-nexus-cyan hover:bg-nexus-cyan/30">
                    {{ t('messages.nexus.developer.webhooks.subscribe') }}
                </button>
            </form>

            @if (count($subscriptions) === 0)
                <p class="text-sm text-nexus-text-muted">{{ t('messages.nexus.developer.webhooks.empty') }}</p>
            @else
                <div class="space-y-3">
                    @foreach ($subscriptions as $subscription)
                        <div class="rounded-md border border-nexus-border bg-nexus-surface-1 p-4">
                            <div class="flex items-center justify-between gap-2">
                                <span class="break-all font-mono text-sm text-nexus-text">{{ $subscription->url }}</span>
                                <x-status-badge :status="$subscription->isRevoked ? 'error' : 'success'" :label="t('messages.nexus.developer.webhooks.status.'.($subscription->isRevoked ? 'revoked' : 'active'))" />
                            </div>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @foreach ($subscription->events as $event)
                                    <x-status-badge status="none" :label="t('messages.nexus.developer.webhooks.event.'.$event)" />
                                @endforeach
                            </div>
                            @unless ($subscription->isRevoked)
                                <form method="POST" action="{{ route('nexus.developer.webhooks.revoke', $subscription->id) }}" class="mt-3">
                                    @csrf
                                    <button type="submit" class="rounded-md border border-nexus-error/40 px-3 py-1 text-xs text-nexus-error hover:bg-nexus-error/10">
                                        {{ t('messages.nexus.developer.webhooks.revoke') }}
                                    </button>
                                </form>
                            @endunless
                        </div>
                    @endforeach
                </div>
            @endif
        </x-nexus-panel>

        <x-nexus-panel :title="t('messages.nexus.developer.webhooks.deliveries_title')">
            @if (count($deliveries) === 0)
                <p class="text-sm text-nexus-text-muted">{{ t('messages.nexus.developer.webhooks.deliveries_empty') }}</p>
            @else
                <div class="space-y-2">
                    @foreach ($deliveries as $delivery)
                        <div class="flex items-center justify-between rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-xs">
                            <span class="text-nexus-text">{{ t('messages.nexus.developer.webhooks.event.'.$delivery->event) }} &rarr; {{ $delivery->url }}</span>
                            <x-status-badge :status="$delivery->succeeded ? 'success' : 'error'" :label="$delivery->succeeded ? (string) $delivery->httpStatus : t('messages.nexus.developer.webhooks.delivery_failed')" />
                        </div>
                    @endforeach
                </div>
            @endif
        </x-nexus-panel>
    </div>
@endsection

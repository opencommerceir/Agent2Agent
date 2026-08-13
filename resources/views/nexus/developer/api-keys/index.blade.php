@extends('nexus::layouts.app')

@section('title', t('messages.nexus.developer.api_keys.title'))

@section('content')
    <div class="mx-auto max-w-3xl space-y-4">
        @if ($plainKey)
            <x-nexus-panel style="border-color: var(--color-nexus-success)">
                <p class="mb-2 text-sm font-semibold text-nexus-success">{{ t('messages.nexus.developer.api_keys.reveal_title') }}</p>
                <p class="mb-2 text-xs text-nexus-text-muted">{{ t('messages.nexus.developer.api_keys.reveal_warning') }}</p>
                <code class="block break-all rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text">{{ $plainKey }}</code>
            </x-nexus-panel>
        @endif

        <x-nexus-panel :title="t('messages.nexus.developer.api_keys.title')">
            <p class="mb-4 text-sm text-nexus-text-muted">{{ t('messages.nexus.developer.api_keys.description') }}</p>

            <form method="POST" action="{{ route('nexus.developer.api-keys.store') }}" class="mb-6 space-y-3 rounded-md border border-nexus-border bg-nexus-surface-1 p-4">
                @csrf
                <div>
                    <label class="mb-1 block text-xs text-nexus-text-muted">{{ t('messages.nexus.developer.api_keys.label') }}</label>
                    <input type="text" name="label" maxlength="100" class="block w-full rounded-md border border-nexus-border bg-nexus-surface-2 px-3 py-1.5 text-sm text-nexus-text" placeholder="{{ t('messages.nexus.developer.api_keys.label_placeholder') }}">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-nexus-text-muted">{{ t('messages.nexus.developer.api_keys.scopes') }}</label>
                    <div class="flex flex-wrap gap-3">
                        @foreach ($scopes as $scope)
                            <label class="flex items-center gap-1.5 text-sm text-nexus-text">
                                <input type="checkbox" name="scopes[]" value="{{ $scope->value }}">
                                {{ t('messages.nexus.developer.api_keys.scope.'.$scope->value) }}
                            </label>
                        @endforeach
                    </div>
                </div>
                <button type="submit" class="rounded-md bg-nexus-cyan/20 px-4 py-1.5 text-sm font-semibold text-nexus-cyan hover:bg-nexus-cyan/30">
                    {{ t('messages.nexus.developer.api_keys.issue') }}
                </button>
            </form>

            @if (count($apiKeys) === 0)
                <p class="text-sm text-nexus-text-muted">{{ t('messages.nexus.developer.api_keys.empty') }}</p>
            @else
                <div class="space-y-3">
                    @foreach ($apiKeys as $apiKey)
                        <div class="rounded-md border border-nexus-border bg-nexus-surface-1 p-4">
                            <div class="flex items-center justify-between">
                                <span class="font-mono text-sm text-nexus-text">{{ $apiKey->keyPrefix }}&hellip;</span>
                                <x-status-badge :status="$apiKey->isRevoked ? 'error' : 'success'" :label="t('messages.nexus.developer.api_keys.status.'.($apiKey->isRevoked ? 'revoked' : 'active'))" />
                            </div>
                            @if ($apiKey->label)
                                <p class="mt-1 text-xs text-nexus-text-muted">{{ $apiKey->label }}</p>
                            @endif
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @foreach ($apiKey->scopes as $scope)
                                    <x-status-badge status="none" :label="t('messages.nexus.developer.api_keys.scope.'.$scope)" />
                                @endforeach
                            </div>
                            <p class="mt-2 text-xs text-nexus-text-muted">
                                {{ t('messages.nexus.developer.api_keys.last_used') }}:
                                {{ $apiKey->lastUsedAt ?? t('messages.nexus.developer.api_keys.never') }}
                            </p>
                            @unless ($apiKey->isRevoked)
                                <form method="POST" action="{{ route('nexus.developer.api-keys.revoke', $apiKey->id) }}" class="mt-3">
                                    @csrf
                                    <button type="submit" class="rounded-md border border-nexus-error/40 px-3 py-1 text-xs text-nexus-error hover:bg-nexus-error/10">
                                        {{ t('messages.nexus.developer.api_keys.revoke') }}
                                    </button>
                                </form>
                            @endunless
                        </div>
                    @endforeach
                </div>
            @endif
        </x-nexus-panel>
    </div>
@endsection

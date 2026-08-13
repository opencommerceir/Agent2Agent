@extends('nexus::layouts.app')

@section('title', t('messages.nexus.developer.integrations.title'))

@section('content')
    <div class="mx-auto max-w-3xl space-y-4">
        @if (session('status'))
            <div class="rounded-md border border-nexus-success/40 bg-nexus-success/10 px-4 py-2 text-sm text-nexus-success">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-md border border-nexus-error/40 bg-nexus-error/10 px-4 py-2 text-sm text-nexus-error">{{ session('error') }}</div>
        @endif

        <x-nexus-panel :title="t('messages.nexus.developer.integrations.title')">
            <p class="mb-4 text-sm text-nexus-text-muted">{{ t('messages.nexus.developer.integrations.description') }}</p>

            <form method="POST" action="{{ route('nexus.developer.integrations.store') }}" class="mb-6 space-y-3 rounded-md border border-nexus-border bg-nexus-surface-1 p-4">
                @csrf
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs text-nexus-text-muted">{{ t('messages.nexus.developer.integrations.category') }}</label>
                        <select name="category" class="block w-full rounded-md border border-nexus-border bg-nexus-surface-2 px-3 py-1.5 text-sm text-nexus-text">
                            @foreach ($categories as $category)
                                <option value="{{ $category->value }}">{{ t('messages.nexus.developer.integrations.category_option.'.$category->value) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-nexus-text-muted">{{ t('messages.nexus.developer.integrations.name') }}</label>
                        <input type="text" name="name" required maxlength="100" class="block w-full rounded-md border border-nexus-border bg-nexus-surface-2 px-3 py-1.5 text-sm text-nexus-text">
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-nexus-text-muted">{{ t('messages.nexus.developer.integrations.target_url') }}</label>
                    <input type="url" name="target_url" required maxlength="2048" class="block w-full rounded-md border border-nexus-border bg-nexus-surface-2 px-3 py-1.5 text-sm text-nexus-text" placeholder="https://your-erp.example.com/api/products">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-nexus-text-muted">{{ t('messages.nexus.developer.integrations.auth_token') }}</label>
                    <input type="text" name="auth_token" maxlength="500" class="block w-full rounded-md border border-nexus-border bg-nexus-surface-2 px-3 py-1.5 text-sm text-nexus-text">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-nexus-text-muted">{{ t('messages.nexus.developer.integrations.field_mapping') }}</label>
                    <p class="mb-2 text-xs text-nexus-text-muted">{{ t('messages.nexus.developer.integrations.field_mapping_hint') }}</p>
                    @for ($i = 0; $i < 5; $i++)
                        <div class="mb-1.5 flex items-center gap-2">
                            <input type="text" name="mapping_source[]" placeholder="{{ t('messages.nexus.developer.integrations.mapping_source_placeholder') }}" class="block w-1/2 rounded-md border border-nexus-border bg-nexus-surface-2 px-3 py-1 text-xs text-nexus-text">
                            <span class="text-nexus-text-muted">&rarr;</span>
                            <input type="text" name="mapping_target[]" placeholder="{{ t('messages.nexus.developer.integrations.mapping_target_placeholder') }}" class="block w-1/2 rounded-md border border-nexus-border bg-nexus-surface-2 px-3 py-1 text-xs text-nexus-text">
                        </div>
                    @endfor
                </div>
                <button type="submit" class="rounded-md bg-nexus-cyan/20 px-4 py-1.5 text-sm font-semibold text-nexus-cyan hover:bg-nexus-cyan/30">
                    {{ t('messages.nexus.developer.integrations.create') }}
                </button>
            </form>

            @if (count($connections) === 0)
                <p class="text-sm text-nexus-text-muted">{{ t('messages.nexus.developer.integrations.empty') }}</p>
            @else
                <div class="space-y-3">
                    @foreach ($connections as $connection)
                        <div class="rounded-md border border-nexus-border bg-nexus-surface-1 p-4">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm text-nexus-text">{{ $connection->name }} — {{ t('messages.nexus.developer.integrations.category_option.'.$connection->category) }}</span>
                                <x-status-badge :status="$connection->isRevoked ? 'error' : 'success'" :label="t('messages.nexus.developer.webhooks.status.'.($connection->isRevoked ? 'revoked' : 'active'))" />
                            </div>
                            <p class="mt-1 break-all font-mono text-xs text-nexus-text-muted">{{ $connection->targetUrl }}</p>
                            @unless ($connection->isRevoked)
                                <div class="mt-3 flex gap-2">
                                    <form method="POST" action="{{ route('nexus.developer.integrations.sync', $connection->id) }}">
                                        @csrf
                                        <button type="submit" class="rounded-md border border-nexus-cyan/40 px-3 py-1 text-xs text-nexus-cyan hover:bg-nexus-cyan/10">
                                            {{ t('messages.nexus.developer.integrations.sync_now') }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('nexus.developer.integrations.revoke', $connection->id) }}">
                                        @csrf
                                        <button type="submit" class="rounded-md border border-nexus-error/40 px-3 py-1 text-xs text-nexus-error hover:bg-nexus-error/10">
                                            {{ t('messages.nexus.developer.integrations.revoke') }}
                                        </button>
                                    </form>
                                </div>
                            @endunless
                        </div>
                    @endforeach
                </div>
            @endif
        </x-nexus-panel>
    </div>
@endsection

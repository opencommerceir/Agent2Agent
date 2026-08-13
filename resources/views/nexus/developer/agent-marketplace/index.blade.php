@extends('nexus::layouts.app')

@section('title', t('messages.nexus.developer.agent_marketplace.title'))

@section('content')
    <div class="mx-auto max-w-4xl space-y-4">
        @if (session('status'))
            <div class="rounded-md border border-nexus-success/40 bg-nexus-success/10 px-4 py-2 text-sm text-nexus-success">{{ session('status') }}</div>
        @endif

        <x-nexus-panel :title="t('messages.nexus.developer.agent_marketplace.title')">
            <p class="text-sm text-nexus-text-muted">{{ t('messages.nexus.developer.agent_marketplace.description') }}</p>
        </x-nexus-panel>

        <x-nexus-panel :title="t('messages.nexus.developer.agent_marketplace.browse')">
            <form method="GET" class="mb-4">
                <input type="text" name="query" value="{{ request('query') }}" placeholder="{{ t('messages.nexus.developer.agent_marketplace.search_placeholder') }}" class="block w-full rounded-md border border-nexus-border bg-nexus-surface-2 px-3 py-1.5 text-sm text-nexus-text">
            </form>

            @if (count($listings) === 0)
                <p class="text-sm text-nexus-text-muted">{{ t('messages.nexus.developer.agent_marketplace.empty') }}</p>
            @else
                <div class="space-y-3">
                    @foreach ($listings as $listing)
                        <div class="rounded-md border border-nexus-border bg-nexus-surface-1 p-4">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm font-semibold text-nexus-text">{{ dashboard_language()->value === 'fa' ? $listing->nameFa : $listing->nameEn }}</span>
                                <x-metric-card :label="t('messages.nexus.developer.agent_marketplace.price')" :value="$listing->priceCredits" />
                            </div>
                            <p class="mt-1 text-xs text-nexus-text-muted">{{ dashboard_language()->value === 'fa' ? $listing->descriptionFa : $listing->descriptionEn }}</p>
                            <p class="mt-2 text-xs text-nexus-text-muted">
                                {{ t('messages.nexus.developer.agent_marketplace.published_by') }}: {{ $publisherNames[$listing->publisherBusinessId] }}
                                &middot; {{ t('messages.nexus.developer.agent_marketplace.installs') }}: {{ $listing->installCount }}
                            </p>
                            <div class="mt-3 flex gap-2">
                                <button type="button" onclick="nexusPreviewTemplate({{ $listing->id }})" class="rounded-md border border-nexus-purple/40 px-3 py-1 text-xs text-nexus-purple hover:bg-nexus-purple/10">
                                    {{ t('messages.nexus.developer.agent_marketplace.preview') }}
                                </button>
                                <form method="POST" action="{{ route('nexus.developer.agent-marketplace.install', $listing->id) }}">
                                    @csrf
                                    <button type="submit" class="rounded-md bg-nexus-cyan/20 px-3 py-1 text-xs font-semibold text-nexus-cyan hover:bg-nexus-cyan/30">
                                        {{ t('messages.nexus.developer.agent_marketplace.install') }}
                                    </button>
                                </form>
                            </div>
                            <pre id="nexus-preview-{{ $listing->id }}" class="mt-2 hidden overflow-x-auto rounded-md border border-nexus-border bg-nexus-surface-2 p-2 text-xs text-nexus-text"></pre>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-nexus-panel>

        <x-nexus-panel :title="t('messages.nexus.developer.agent_marketplace.publish_title')">
            <form method="POST" action="{{ route('nexus.developer.agent-marketplace.publish') }}" class="space-y-3 rounded-md border border-nexus-border bg-nexus-surface-1 p-4">
                @csrf
                <div class="grid gap-3 sm:grid-cols-2">
                    <input type="text" name="name_fa" required maxlength="150" placeholder="{{ t('messages.nexus.developer.agent_marketplace.name_fa') }}" class="block w-full rounded-md border border-nexus-border bg-nexus-surface-2 px-3 py-1.5 text-sm text-nexus-text">
                    <input type="text" name="name_en" required maxlength="150" placeholder="{{ t('messages.nexus.developer.agent_marketplace.name_en') }}" class="block w-full rounded-md border border-nexus-border bg-nexus-surface-2 px-3 py-1.5 text-sm text-nexus-text">
                </div>
                <textarea name="description_fa" required maxlength="2000" rows="2" placeholder="{{ t('messages.nexus.developer.agent_marketplace.description_fa') }}" class="block w-full rounded-md border border-nexus-border bg-nexus-surface-2 px-3 py-1.5 text-sm text-nexus-text"></textarea>
                <textarea name="description_en" required maxlength="2000" rows="2" placeholder="{{ t('messages.nexus.developer.agent_marketplace.description_en') }}" class="block w-full rounded-md border border-nexus-border bg-nexus-surface-2 px-3 py-1.5 text-sm text-nexus-text"></textarea>
                <div class="grid gap-3 sm:grid-cols-2">
                    <input type="text" name="personality" maxlength="500" placeholder="{{ t('messages.nexus.developer.agent_marketplace.personality') }}" class="block w-full rounded-md border border-nexus-border bg-nexus-surface-2 px-3 py-1.5 text-sm text-nexus-text">
                    <input type="text" name="tone" maxlength="100" placeholder="{{ t('messages.nexus.developer.agent_marketplace.tone') }}" class="block w-full rounded-md border border-nexus-border bg-nexus-surface-2 px-3 py-1.5 text-sm text-nexus-text">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-nexus-text-muted">{{ t('messages.nexus.developer.agent_marketplace.strategies_json') }}</label>
                    <textarea name="strategies_json" required rows="3" placeholder='{"opening_discount_percent": 5}' class="block w-full rounded-md border border-nexus-border bg-nexus-surface-2 px-3 py-1.5 font-mono text-xs text-nexus-text"></textarea>
                </div>
                <input type="number" name="price_credits" required min="0" placeholder="{{ t('messages.nexus.developer.agent_marketplace.price_credits') }}" class="block w-full rounded-md border border-nexus-border bg-nexus-surface-2 px-3 py-1.5 text-sm text-nexus-text">
                <button type="submit" class="rounded-md bg-nexus-cyan/20 px-4 py-1.5 text-sm font-semibold text-nexus-cyan hover:bg-nexus-cyan/30">
                    {{ t('messages.nexus.developer.agent_marketplace.publish') }}
                </button>
            </form>

            @if (count($myTemplates) > 0)
                <div class="mt-4 space-y-2">
                    @foreach ($myTemplates as $template)
                        <div class="flex items-center justify-between rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-xs">
                            <span class="text-nexus-text">{{ $template->nameEn }} &mdash; {{ $template->installCount }} {{ t('messages.nexus.developer.agent_marketplace.installs') }}</span>
                            <div class="flex items-center gap-2">
                                <x-status-badge :status="$template->isRevoked ? 'error' : 'success'" :label="t('messages.nexus.developer.webhooks.status.'.($template->isRevoked ? 'revoked' : 'active'))" />
                                @unless ($template->isRevoked)
                                    <form method="POST" action="{{ route('nexus.developer.agent-marketplace.unpublish', $template->id) }}">
                                        @csrf
                                        <button type="submit" class="rounded-md border border-nexus-error/40 px-2 py-0.5 text-xs text-nexus-error hover:bg-nexus-error/10">
                                            {{ t('messages.nexus.developer.agent_marketplace.unpublish') }}
                                        </button>
                                    </form>
                                @endunless
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-nexus-panel>
    </div>

    <script>
        async function nexusPreviewTemplate(templateId) {
            const el = document.getElementById('nexus-preview-' + templateId);
            const response = await fetch('/nexus/developer/agent-marketplace/' + templateId + '/preview');
            const data = await response.json();
            el.textContent = JSON.stringify(data, null, 2);
            el.classList.remove('hidden');
        }
    </script>
@endsection

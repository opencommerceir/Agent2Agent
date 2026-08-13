@extends('nexus::layouts.app')

@section('title', t('messages.nexus.developer.docs.title'))

@section('content')
    <div class="mx-auto max-w-4xl space-y-4">
        <x-nexus-panel :title="t('messages.nexus.developer.docs.title')">
            <p class="text-sm text-nexus-text-muted">{{ t('messages.nexus.developer.docs.intro') }}</p>
        </x-nexus-panel>

        <x-nexus-panel :title="t('messages.nexus.developer.docs.auth.title')">
            <p class="mb-2 text-sm text-nexus-text-muted">{{ t('messages.nexus.developer.docs.auth.description') }}</p>
            <pre class="overflow-x-auto rounded-md border border-nexus-border bg-nexus-surface-1 p-3 text-xs text-nexus-text">curl https://your-domain/nexus/api/v1/business \
  -H "Authorization: Bearer nx_your_api_key"</pre>
            <p class="mt-2 text-xs text-nexus-text-muted">
                {{ t('messages.nexus.developer.docs.auth.rate_limit', ['limit' => $rateLimitPerMinute]) }}
            </p>
        </x-nexus-panel>

        <x-nexus-panel :title="t('messages.nexus.developer.docs.scopes.title')">
            <div class="flex flex-wrap gap-1.5">
                @foreach ($scopes as $scope)
                    <x-status-badge status="none" :label="$scope->value" />
                @endforeach
            </div>
        </x-nexus-panel>

        <x-nexus-panel :title="t('messages.nexus.developer.docs.endpoints.title')">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-nexus-text-muted">
                            <th class="pb-2 pr-3">{{ t('messages.nexus.developer.docs.endpoints.method') }}</th>
                            <th class="pb-2 pr-3">{{ t('messages.nexus.developer.docs.endpoints.path') }}</th>
                            <th class="pb-2 pr-3">{{ t('messages.nexus.developer.docs.endpoints.scope') }}</th>
                            <th class="pb-2">{{ t('messages.nexus.developer.docs.endpoints.description') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($endpoints as $endpoint)
                            <tr class="border-t border-nexus-border">
                                <td class="py-2 pr-3 font-mono text-nexus-cyan">{{ $endpoint['method'] }}</td>
                                <td class="py-2 pr-3 font-mono text-nexus-text">{{ $endpoint['path'] }}</td>
                                <td class="py-2 pr-3 font-mono text-nexus-purple">{{ $endpoint['scope'] }}</td>
                                <td class="py-2 text-nexus-text-muted">{{ $endpoint['description'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-nexus-panel>

        <x-nexus-panel :title="t('messages.nexus.developer.docs.errors.title')">
            <p class="mb-2 text-sm text-nexus-text-muted">{{ t('messages.nexus.developer.docs.errors.description') }}</p>
            <pre class="overflow-x-auto rounded-md border border-nexus-border bg-nexus-surface-1 p-3 text-xs text-nexus-text">{
  "error": {
    "code": "FORBIDDEN",
    "message": "This API key is missing the required 'catalog.read' scope.",
    "localized_message": "..."
  }
}</pre>
        </x-nexus-panel>

        <x-nexus-panel :title="t('messages.nexus.developer.docs.webhooks.title')">
            <p class="mb-2 text-sm text-nexus-text-muted">{{ t('messages.nexus.developer.docs.webhooks.description') }}</p>
            <div class="mb-3 flex flex-wrap gap-1.5">
                @foreach ($webhookEvents as $event)
                    <x-status-badge status="none" :label="$event->value" />
                @endforeach
            </div>
            <p class="mb-2 text-xs text-nexus-text-muted">{{ t('messages.nexus.developer.docs.webhooks.payload_shape') }}</p>
            <pre class="mb-3 overflow-x-auto rounded-md border border-nexus-border bg-nexus-surface-1 p-3 text-xs text-nexus-text">{
  "event": "negotiation.accepted",
  "data": { "...": "..." },
  "timestamp": "2026-08-13T12:00:00+00:00"
}</pre>
            <p class="mb-2 text-xs text-nexus-text-muted">{{ t('messages.nexus.developer.docs.webhooks.signature') }}</p>
            <pre class="overflow-x-auto rounded-md border border-nexus-border bg-nexus-surface-1 p-3 text-xs text-nexus-text">$expected = 'sha256=' . hash_hmac('sha256', $rawRequestBody, $yourWebhookSecret);
hash_equals($expected, $_SERVER['HTTP_X_NEXUS_SIGNATURE']);</pre>
        </x-nexus-panel>
    </div>
@endsection

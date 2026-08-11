@extends('nexus::layouts.app')

@section('title', $owner->name)

@section('content')
    <div class="mx-auto max-w-4xl space-y-4">
        <x-nexus-panel>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-nexus-text">{{ dashboard_language()->value === 'fa' ? $business->nameFa() : $business->nameEn() }}</p>
                    <p class="text-xs text-nexus-text-muted">{{ $owner->name }} — {{ $owner->email }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <x-status-badge :status="$business->isVerified() ? 'success' : 'warning'" :label="t('messages.nexus.business.dashboard.status.'.$business->verificationStatus()->value)" />
                    <form method="POST" action="{{ route('nexus.business.logout') }}">
                        @csrf
                        <button type="submit" class="rounded-md border border-nexus-border px-3 py-1.5 text-sm text-nexus-text hover:bg-nexus-surface-1">
                            {{ t('messages.nav.logout') }}
                        </button>
                    </form>
                </div>
            </div>
        </x-nexus-panel>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-nexus-panel :title="t('messages.nexus.business.dashboard.agent')">
                @if ($agent)
                    <div class="space-y-2">
                        <x-agent-pulse status="active" :label="dashboard_language()->value === 'fa' ? $agent->nameFa() : $agent->nameEn()" />
                        <p class="text-xs text-nexus-text-muted">
                            {{ $agent->tone() ?? t('messages.nexus.business.dashboard.agent_no_personality') }}
                        </p>
                    </div>
                @else
                    <div class="space-y-2">
                        <x-agent-pulse status="idle" :label="t('messages.nexus.business.dashboard.agent_pending')" />
                        <p class="text-xs text-nexus-text-muted">{{ t('messages.nexus.business.dashboard.agent_pending_description') }}</p>
                    </div>
                @endif
            </x-nexus-panel>

            <x-nexus-panel :title="t('messages.nexus.business.dashboard.catalog')">
                <div class="grid grid-cols-2 gap-3">
                    <x-metric-card :label="t('messages.nexus.business.dashboard.products')" :value="$productCount" />
                    <x-metric-card :label="t('messages.nexus.business.dashboard.services')" :value="$serviceCount" />
                </div>
            </x-nexus-panel>

            <x-nexus-panel :title="t('messages.nexus.business.dashboard.credit')">
                <x-metric-card :label="t('messages.nexus.business.dashboard.credit_balance')" :value="$creditBalance ?? '—'" />
            </x-nexus-panel>

            <x-nexus-panel :title="t('messages.nexus.business.dashboard.negotiations')">
                <x-metric-card :label="t('messages.nexus.business.dashboard.active_negotiations')" :value="$activeNegotiations ?? '—'" />
            </x-nexus-panel>
        </div>
    </div>
@endsection

@extends('nexus::layouts.app')

@section('title', $owner->name)

@section('content')
    <div class="mx-auto max-w-4xl space-y-4">
        @if (session('status'))
            <div class="rounded-md border border-nexus-success/40 bg-nexus-success/10 px-4 py-2 text-sm text-nexus-success">{{ session('status') }}</div>
        @endif

        @if ($business->status()->value === 'suspended')
            <x-nexus-panel style="border-color: var(--color-nexus-error)">
                <p class="mb-3 text-sm text-nexus-error">{{ t('messages.nexus.business.dashboard.suspended_banner') }}</p>
                <form method="POST" action="{{ route('nexus.business.dashboard.appeal') }}" class="space-y-2">
                    @csrf
                    <textarea name="message" rows="3" required class="block w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-1.5 text-sm text-nexus-text" placeholder="{{ t('messages.nexus.business.dashboard.appeal_placeholder') }}"></textarea>
                    <button type="submit" class="rounded-md bg-nexus-cyan/20 px-4 py-1.5 text-sm font-semibold text-nexus-cyan hover:bg-nexus-cyan/30">
                        {{ t('messages.nexus.business.dashboard.submit_appeal') }}
                    </button>
                </form>
            </x-nexus-panel>
        @endif

        <x-nexus-panel>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-nexus-text">{{ dashboard_language()->value === 'fa' ? $business->nameFa() : $business->nameEn() }}</p>
                    <p class="text-xs text-nexus-text-muted">{{ $owner->name }} — {{ $owner->email }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <x-status-badge :status="$business->isVerified() ? 'success' : 'warning'" :label="t('messages.nexus.business.dashboard.status.'.$business->verificationStatus()->value)" />
                    <a href="{{ route('nexus.analytics.index') }}" class="rounded-md border border-nexus-purple/40 px-3 py-1.5 text-sm text-nexus-purple hover:bg-nexus-purple/10">
                        {{ t('messages.nexus.analytics.title') }}
                    </a>
                    <a href="{{ route('nexus.automation.index') }}" class="rounded-md border border-nexus-purple/40 px-3 py-1.5 text-sm text-nexus-purple hover:bg-nexus-purple/10">
                        {{ t('messages.nexus.automation.title') }}
                    </a>
                    <a href="{{ route('nexus.growth.referrals.index') }}" class="rounded-md border border-nexus-purple/40 px-3 py-1.5 text-sm text-nexus-purple hover:bg-nexus-purple/10">
                        {{ t('messages.nexus.growth.referrals.title') }}
                    </a>
                    <a href="{{ route('nexus.holding.index') }}" class="rounded-md border border-nexus-purple/40 px-3 py-1.5 text-sm text-nexus-purple hover:bg-nexus-purple/10">
                        {{ t('messages.nexus.holding.title') }}
                    </a>
                    <a href="{{ route('nexus.business.team.index') }}" class="rounded-md border border-nexus-purple/40 px-3 py-1.5 text-sm text-nexus-purple hover:bg-nexus-purple/10">
                        {{ t('messages.nexus.business.team.title') }}
                    </a>
                    <a href="{{ route('nexus.business.approval-policy.edit') }}" class="rounded-md border border-nexus-purple/40 px-3 py-1.5 text-sm text-nexus-purple hover:bg-nexus-purple/10">
                        {{ t('messages.nexus.business.approval_policy.title') }}
                    </a>
                    <a href="{{ route('nexus.private-marketplace.index') }}" class="rounded-md border border-nexus-purple/40 px-3 py-1.5 text-sm text-nexus-purple hover:bg-nexus-purple/10">
                        {{ t('messages.nexus.private_marketplace.title') }}
                    </a>
                    <a href="{{ route('nexus.business.sessions.index') }}" class="rounded-md border border-nexus-purple/40 px-3 py-1.5 text-sm text-nexus-purple hover:bg-nexus-purple/10">
                        {{ t('messages.nexus.business.sessions.title') }}
                    </a>
                    <a href="{{ route('nexus.business.mfa.edit') }}" class="rounded-md border border-nexus-purple/40 px-3 py-1.5 text-sm text-nexus-purple hover:bg-nexus-purple/10">
                        {{ t('messages.nexus.business.mfa.settings.title') }}
                    </a>
                    <a href="{{ route('nexus.developer.api-keys.index') }}" class="rounded-md border border-nexus-purple/40 px-3 py-1.5 text-sm text-nexus-purple hover:bg-nexus-purple/10">
                        {{ t('messages.nexus.developer.api_keys.title') }}
                    </a>
                    <a href="{{ route('nexus.developer.webhooks.index') }}" class="rounded-md border border-nexus-purple/40 px-3 py-1.5 text-sm text-nexus-purple hover:bg-nexus-purple/10">
                        {{ t('messages.nexus.developer.webhooks.title') }}
                    </a>
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

            <x-nexus-panel :title="t('messages.nexus.business.dashboard.reputation')">
                <div class="flex items-center gap-3">
                    <x-metric-card :label="t('messages.nexus.business.dashboard.reputation_score')" :value="$reputationScore->score" />
                    @if ($reputationScore->reviewCount > 0)
                        <span class="text-xs text-nexus-text-muted">{{ $reputationScore->averageRating }}/5 ({{ $reputationScore->reviewCount }})</span>
                    @endif
                </div>
                @if ($reputationScore->badges !== [])
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        @foreach ($reputationScore->badges as $badge)
                            <x-status-badge status="success" :label="t('messages.nexus.reputation.badges.'.$badge)" />
                        @endforeach
                    </div>
                @endif
            </x-nexus-panel>

            <x-nexus-panel :title="t('messages.nexus.business.dashboard.data_residency.title')">
                <p class="mb-2 text-xs text-nexus-text-muted">{{ t('messages.nexus.business.dashboard.data_residency.description') }}</p>
                <p class="mb-3 text-xs text-nexus-text-muted">
                    {{ t('messages.nexus.business.dashboard.data_residency.current') }}:
                    <span class="font-medium text-nexus-text">
                        {{ $business->dataResidencyRegion() ? t('messages.nexus.business.dashboard.data_residency.region.'.$business->dataResidencyRegion()->value) : t('messages.nexus.business.dashboard.data_residency.not_declared') }}
                    </span>
                </p>
                <form method="POST" action="{{ route('nexus.business.dashboard.data-residency') }}" class="flex items-center gap-2">
                    @csrf
                    <select name="data_residency_region" class="rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-1.5 text-sm text-nexus-text">
                        @foreach (['ir', 'eu', 'us', 'gcc', 'other'] as $region)
                            <option value="{{ $region }}" @selected($business->dataResidencyRegion()?->value === $region)>
                                {{ t('messages.nexus.business.dashboard.data_residency.region.'.$region) }}
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-md bg-nexus-cyan/20 px-4 py-1.5 text-sm font-semibold text-nexus-cyan hover:bg-nexus-cyan/30">
                        {{ t('messages.nexus.business.dashboard.data_residency.save') }}
                    </button>
                </form>
            </x-nexus-panel>
        </div>
    </div>
@endsection

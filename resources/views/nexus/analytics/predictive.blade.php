@extends('nexus::layouts.app')

@section('title', t('messages.nexus.analytics.predictive.title'))

@section('content')
    <div class="mx-auto max-w-4xl space-y-4">
        <x-nexus-panel :title="t('messages.nexus.analytics.predictive.forecast_title')">
            <form method="GET" class="mb-3 flex items-end gap-2">
                <div>
                    <label class="mb-1 block text-xs text-nexus-text-muted">{{ t('messages.nexus.analytics.predictive.business_id') }}</label>
                    <input type="number" name="forecast_for" value="{{ $forecast['businessId'] }}" class="rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-1.5 text-sm text-nexus-text">
                </div>
                <button type="submit" class="rounded-md border border-nexus-cyan/40 px-3 py-1.5 text-sm text-nexus-cyan hover:bg-nexus-cyan/10">{{ t('messages.nexus.analytics.predictive.check') }}</button>
            </form>
            <div class="grid grid-cols-3 gap-3">
                <x-metric-card :label="t('messages.nexus.analytics.predictive.current_score')" :value="$forecast['currentScore']" />
                <x-metric-card :label="t('messages.nexus.analytics.predictive.trend')" :value="t('messages.nexus.analytics.predictive.trend_value.'.$forecast['trend'])" />
                <x-metric-card :label="t('messages.nexus.analytics.predictive.recent_success_rate')" :value="$forecast['recentSuccessRate'] !== null ? round($forecast['recentSuccessRate'] * 100, 1).'%' : '—'" />
            </div>
        </x-nexus-panel>

        <x-nexus-panel :title="t('messages.nexus.analytics.predictive.risk_title')">
            <form method="GET" class="mb-3 grid grid-cols-4 gap-2 items-end">
                <input type="hidden" name="forecast_for" value="{{ $forecast['businessId'] }}">
                <div>
                    <label class="mb-1 block text-xs text-nexus-text-muted">{{ t('messages.nexus.analytics.predictive.business_id') }}</label>
                    <input type="number" name="risk_for" value="{{ $riskForId }}" class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-1.5 text-sm text-nexus-text">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-nexus-text-muted">{{ t('messages.nexus.analytics.predictive.deal_amount') }}</label>
                    <input type="number" name="deal_amount" class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-1.5 text-sm text-nexus-text">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-nexus-text-muted">{{ t('messages.nexus.analytics.predictive.currency') }}</label>
                    <input type="text" name="deal_currency" maxlength="3" value="IRT" class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-1.5 text-sm text-nexus-text">
                </div>
                <button type="submit" class="rounded-md border border-nexus-cyan/40 px-3 py-1.5 text-sm text-nexus-cyan hover:bg-nexus-cyan/10">{{ t('messages.nexus.analytics.predictive.check') }}</button>
            </form>
            @if ($risk)
                <div class="grid grid-cols-3 gap-3">
                    <x-metric-card :label="t('messages.nexus.analytics.predictive.risk_score')" :value="$risk['riskScore']" />
                    <x-metric-card :label="t('messages.nexus.analytics.predictive.risk_level')" :value="t('messages.nexus.analytics.predictive.risk_level_value.'.$risk['riskLevel'])" />
                    <x-metric-card :label="t('messages.nexus.analytics.predictive.disputes_recent')" :value="$risk['disputesLostRecent']" />
                </div>
            @endif
        </x-nexus-panel>

        <x-nexus-panel :title="t('messages.nexus.analytics.predictive.scenario_title')">
            <form method="GET" class="mb-3 grid grid-cols-4 gap-2 items-end">
                <input type="hidden" name="forecast_for" value="{{ $forecast['businessId'] }}">
                <div>
                    <label class="mb-1 block text-xs text-nexus-text-muted">{{ t('messages.nexus.analytics.predictive.business_id') }}</label>
                    <input type="number" name="scenario_for" value="{{ $scenarioForId }}" class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-1.5 text-sm text-nexus-text">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-nexus-text-muted">{{ t('messages.nexus.analytics.predictive.hypothetical_amount') }}</label>
                    <input type="number" name="hypothetical_amount" class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-1.5 text-sm text-nexus-text">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-nexus-text-muted">{{ t('messages.nexus.analytics.price_benchmark.product') }}/{{ t('messages.nexus.analytics.price_benchmark.service') }}</label>
                    <select name="catalog_item_type" class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-1.5 text-sm text-nexus-text">
                        <option value="product">product</option>
                        <option value="service">service</option>
                    </select>
                </div>
                <button type="submit" class="rounded-md border border-nexus-cyan/40 px-3 py-1.5 text-sm text-nexus-cyan hover:bg-nexus-cyan/10">{{ t('messages.nexus.analytics.predictive.check') }}</button>
            </form>
            @if ($scenario)
                @if ($scenario['estimatedAcceptanceLikelihood'] === null)
                    <p class="text-sm text-nexus-text-muted">{{ t('messages.nexus.analytics.predictive.no_history') }}</p>
                @else
                    <div class="grid grid-cols-2 gap-3">
                        <x-metric-card :label="t('messages.nexus.analytics.predictive.acceptance_likelihood')" :value="round($scenario['estimatedAcceptanceLikelihood'] * 100, 1).'%'" />
                        <x-metric-card :label="t('messages.nexus.analytics.predictive.baseline_price')" :value="number_format($scenario['baselineAverageUnitAmount']).' '.$scenario['currency']" />
                    </div>
                @endif
            @endif
        </x-nexus-panel>
    </div>
@endsection

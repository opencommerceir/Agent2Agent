@extends('nexus::layouts.app')

@section('title', t('messages.nexus.analytics.market.title'))

@section('content')
    <div class="mx-auto max-w-4xl space-y-4">
        <x-nexus-panel>
            <div class="flex items-center justify-between gap-3">
                <h1 class="text-sm text-nexus-text">{{ t('messages.nexus.analytics.market.title') }}</h1>
                <form method="GET" class="flex items-center gap-2">
                    <select name="industry" class="rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-1.5 text-sm text-nexus-text" onchange="this.form.submit()">
                        @foreach (\App\Domains\Nexus\Business\Domain\ValueObjects\Industry::cases() as $case)
                            <option value="{{ $case->value }}" @selected($market['industry'] === $case->value)>
                                {{ t('messages.nexus.business.industry.'.$case->value) }}
                            </option>
                        @endforeach
                    </select>
                </form>
            </div>
        </x-nexus-panel>

        <x-nexus-panel :title="t('messages.nexus.analytics.market.competitor_stats')">
            @if ($market['competitorStats']['averageProductPriceAmount'] === null && $market['competitorStats']['averageServicePriceAmount'] === null)
                <p class="text-sm text-nexus-text-muted">{{ t('messages.nexus.analytics.market.insufficient_data', ['count' => $market['competitorStats']['competitorCount']]) }}</p>
            @else
                <div class="grid grid-cols-3 gap-3">
                    <x-metric-card :label="t('messages.nexus.analytics.market.competitors')" :value="$market['competitorStats']['competitorCount']" />
                    @if ($market['competitorStats']['averageProductPriceAmount'] !== null)
                        <x-metric-card :label="t('messages.nexus.analytics.price_benchmark.product')" :value="number_format($market['competitorStats']['averageProductPriceAmount']).' '.$market['competitorStats']['currency']" />
                    @endif
                    @if ($market['competitorStats']['averageSuccessRatePercent'] !== null)
                        <x-metric-card :label="t('messages.nexus.analytics.success_rate')" :value="$market['competitorStats']['averageSuccessRatePercent'].'%'" />
                    @endif
                </div>
            @endif
        </x-nexus-panel>

        <x-nexus-panel :title="t('messages.nexus.analytics.market.demand_signal')">
            @if (count($market['demandSignal']) === 0)
                <p class="text-sm text-nexus-text-muted">{{ t('messages.nexus.analytics.market.empty') }}</p>
            @else
                <div class="space-y-1.5">
                    @foreach ($market['demandSignal'] as $week)
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-nexus-text-muted">{{ $week['weekStart'] }}</span>
                            <span class="font-mono text-nexus-text">{{ $week['proposalsCount'] }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-nexus-panel>

        <x-nexus-panel :title="t('messages.nexus.analytics.market.price_trend')">
            @if (count($market['priceTrend']) === 0)
                <p class="text-sm text-nexus-text-muted">{{ t('messages.nexus.analytics.market.empty') }}</p>
            @else
                <div class="space-y-1.5">
                    @foreach ($market['priceTrend'] as $week)
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-nexus-text-muted">{{ $week['weekStart'] }}</span>
                            <span class="font-mono text-nexus-text">{{ number_format($week['averageUnitAmount']) }} {{ $week['currency'] }} ({{ $week['dealCount'] }})</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-nexus-panel>
    </div>
@endsection

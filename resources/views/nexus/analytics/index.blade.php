@extends('nexus::layouts.app')

@section('title', t('messages.nexus.analytics.title'))

@section('content')
    <div class="mx-auto max-w-4xl space-y-4">
        <x-nexus-panel>
            <div class="flex items-center justify-between">
                <h1 class="text-sm text-nexus-text">{{ t('messages.nexus.analytics.title') }}</h1>
                <div class="flex gap-2">
                    <a href="{{ route('nexus.analytics.market') }}" class="rounded-md border border-nexus-purple/40 px-3 py-1.5 text-sm text-nexus-purple hover:bg-nexus-purple/10">
                        {{ t('messages.nexus.analytics.market.title') }}
                    </a>
                    <a href="{{ route('nexus.analytics.predictive') }}" class="rounded-md border border-nexus-purple/40 px-3 py-1.5 text-sm text-nexus-purple hover:bg-nexus-purple/10">
                        {{ t('messages.nexus.analytics.predictive.title') }}
                    </a>
                    <a href="{{ route('nexus.analytics.export') }}" class="rounded-md border border-nexus-cyan/40 px-3 py-1.5 text-sm text-nexus-cyan hover:bg-nexus-cyan/10">
                        {{ t('messages.nexus.analytics.export') }}
                    </a>
                </div>
            </div>
        </x-nexus-panel>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-nexus-panel>
                <div class="grid grid-cols-2 gap-3">
                    <x-metric-card :label="t('messages.nexus.analytics.success_rate')" :value="round($analytics['successRate'] * 100, 1).'%'" />
                    <x-metric-card :label="t('messages.nexus.analytics.completed_deals')" :value="$analytics['completedDeals']" />
                </div>
            </x-nexus-panel>

            <x-nexus-panel>
                <div class="grid grid-cols-4 gap-3">
                    <x-metric-card :label="t('messages.nexus.analytics.deal_counts.accepted')" :value="$analytics['dealCounts']['accepted']" />
                    <x-metric-card :label="t('messages.nexus.analytics.deal_counts.rejected')" :value="$analytics['dealCounts']['rejected']" />
                    <x-metric-card :label="t('messages.nexus.analytics.deal_counts.expired')" :value="$analytics['dealCounts']['expired']" />
                    <x-metric-card :label="t('messages.nexus.analytics.deal_counts.open')" :value="$analytics['dealCounts']['open']" />
                </div>
            </x-nexus-panel>

            <x-nexus-panel :title="t('messages.nexus.analytics.savings.title')">
                @if ($analytics['savings']['dealCount'] === 0)
                    <p class="text-sm text-nexus-text-muted">{{ t('messages.nexus.analytics.savings.empty') }}</p>
                @else
                    <p class="text-xs text-nexus-text-muted mb-2">{{ t('messages.nexus.analytics.savings.deal_count', ['count' => $analytics['savings']['dealCount']]) }}</p>
                    <div class="grid grid-cols-2 gap-3">
                        @foreach ($analytics['savings']['totalsByCurrency'] as $currency => $amount)
                            <x-metric-card :label="t('messages.nexus.analytics.savings.total').' ('.$currency.')'" :value="number_format($amount)" />
                        @endforeach
                    </div>
                @endif
            </x-nexus-panel>

            <x-nexus-panel :title="t('messages.nexus.analytics.price_benchmark.title')">
                @foreach (['product', 'service'] as $type)
                    @php($benchmark = $analytics['priceBenchmark'][$type])
                    <div class="mb-3 last:mb-0">
                        <p class="mb-1 text-xs font-semibold text-nexus-text">{{ t('messages.nexus.analytics.price_benchmark.'.$type) }}</p>
                        @if ($benchmark['ownAverageAmount'] === null)
                            <p class="text-xs text-nexus-text-muted">—</p>
                        @else
                            <div class="grid grid-cols-2 gap-3">
                                <x-metric-card :label="t('messages.nexus.analytics.price_benchmark.your_average')" :value="number_format($benchmark['ownAverageAmount']).' '.$benchmark['currency']" />
                                @if ($benchmark['industryAverageAmount'] !== null)
                                    <x-metric-card :label="t('messages.nexus.analytics.price_benchmark.industry_average')" :value="number_format($benchmark['industryAverageAmount']).' '.$benchmark['currency']" />
                                @else
                                    <x-metric-card :label="t('messages.nexus.analytics.price_benchmark.industry_average')" :value="t('messages.nexus.analytics.price_benchmark.insufficient_data')" />
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </x-nexus-panel>
        </div>
    </div>
@endsection

@extends('nexus::layouts.app')

@section('title', t('messages.nexus.marketplace.recommendations.title'))

@section('content')
    <div class="mx-auto max-w-4xl space-y-4">
        <x-nexus-panel :title="t('messages.nexus.marketplace.recommendations.title')">
            @if (count($recommendations['listings']) === 0)
                <p class="text-sm text-nexus-text-muted">{{ t('messages.nexus.marketplace.recommendations.empty') }}</p>
            @else
                <div class="space-y-2">
                    @foreach ($recommendations['listings'] as $listing)
                        <div class="flex items-center justify-between rounded-md border border-nexus-border bg-nexus-surface-1 p-3">
                            <span class="text-sm text-nexus-text">{{ dashboard_language()->value === 'fa' ? $listing['nameFa'] : $listing['nameEn'] }}</span>
                            <div class="flex items-center gap-2">
                                <a href="{{ route('nexus.recommendations.index', ['alternative_to' => $listing['businessId']]) }}" class="text-xs text-nexus-purple hover:underline">
                                    {{ t('messages.nexus.marketplace.recommendations.find_alternatives') }}
                                </a>
                                <a href="{{ route('nexus.recommendations.index', ['timing_for' => $listing['businessId']]) }}" class="text-xs text-nexus-cyan hover:underline">
                                    {{ t('messages.nexus.marketplace.recommendations.best_timing') }}
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-nexus-panel>

        @if ($alternativeToId)
            <x-nexus-panel :title="t('messages.nexus.marketplace.recommendations.alternatives_to', ['id' => $alternativeToId])">
                @if (count($alternatives['listings']) === 0)
                    <p class="text-sm text-nexus-text-muted">{{ t('messages.nexus.marketplace.recommendations.empty') }}</p>
                @else
                    <div class="space-y-2">
                        @foreach ($alternatives['listings'] as $listing)
                            <div class="rounded-md border border-nexus-border bg-nexus-surface-1 p-3 text-sm text-nexus-text">
                                {{ dashboard_language()->value === 'fa' ? $listing['nameFa'] : $listing['nameEn'] }}
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-nexus-panel>
        @endif

        @if ($timingForId)
            <x-nexus-panel :title="t('messages.nexus.marketplace.recommendations.timing_for', ['id' => $timingForId])">
                @if ($timing['sampleSize'] === 0)
                    <p class="text-sm text-nexus-text-muted">{{ t('messages.nexus.marketplace.recommendations.no_history') }}</p>
                @else
                    <div class="space-y-1.5">
                        @foreach ($timing['byDayOfWeek'] as $day)
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-nexus-text-muted">{{ t('messages.nexus.marketplace.recommendations.day.'.$day['dayOfWeek']) }}</span>
                                <span class="font-mono text-nexus-text">{{ $day['acceptanceRatePercent'] }}% ({{ $day['acceptedCount'] }}/{{ $day['dealCount'] }})</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-nexus-panel>
        @endif
    </div>
@endsection

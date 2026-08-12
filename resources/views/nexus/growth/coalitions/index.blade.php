@extends('nexus::layouts.app')

@section('title', t('messages.nexus.growth.coalitions.title'))

@section('content')
    <div class="mx-auto max-w-3xl space-y-4">
        <x-nexus-panel :title="t('messages.nexus.growth.coalitions.title')">
            <div class="mb-4 flex items-center justify-between">
                <p class="text-sm text-nexus-text-muted">{{ t('messages.nexus.growth.coalitions.how_it_works') }}</p>
                <div class="flex shrink-0 gap-2">
                    <a href="{{ route('nexus.network.index') }}" class="rounded-md border border-nexus-cyan/40 px-3 py-1.5 text-sm text-nexus-cyan hover:bg-nexus-cyan/10">
                        {{ t('messages.nexus.network.title') }}
                    </a>
                    <a href="{{ route('nexus.growth.coalitions.create') }}" class="rounded-md bg-nexus-cyan/20 px-3 py-1.5 text-sm font-semibold text-nexus-cyan hover:bg-nexus-cyan/30">
                        {{ t('messages.nexus.growth.coalitions.create_new') }}
                    </a>
                </div>
            </div>

            @if (count($coalitions) === 0)
                <p class="text-sm text-nexus-text-muted">{{ t('messages.nexus.growth.coalitions.empty') }}</p>
            @else
                <div class="space-y-3">
                    @foreach ($coalitions as $coalition)
                        <a href="{{ route('nexus.growth.coalitions.show', $coalition->id) }}" class="block rounded-md border border-nexus-border bg-nexus-surface-1 p-4 hover:border-nexus-cyan/40">
                            <div class="flex items-center justify-between">
                                <span class="font-mono text-sm text-nexus-text">{{ t('messages.nexus.growth.coalitions.catalog_item') }} #{{ $coalition->catalogItemId }} ({{ $coalition->catalogItemType }})</span>
                                <x-status-badge status="warning" :label="t('messages.nexus.growth.coalitions.status.'.$coalition->status)" />
                            </div>
                            <p class="mt-1 text-xs text-nexus-text-muted">
                                {{ t('messages.nexus.growth.coalitions.discount') }}: {{ $coalition->discountPercent }}% ·
                                {{ t('messages.nexus.growth.coalitions.members') }}: {{ count($coalition->members) }}/{{ $coalition->minParticipants }}
                            </p>
                        </a>
                    @endforeach
                </div>
            @endif
        </x-nexus-panel>
    </div>
@endsection

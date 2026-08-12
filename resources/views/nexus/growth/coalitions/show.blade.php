@extends('nexus::layouts.app')

@section('title', t('messages.nexus.growth.coalitions.title'))

@section('content')
    <div class="mx-auto max-w-2xl space-y-4">
        <x-nexus-panel>
            <div class="mb-4 flex items-center justify-between">
                <span class="font-mono text-sm text-nexus-text">{{ t('messages.nexus.growth.coalitions.catalog_item') }} #{{ $coalition->catalogItemId }} ({{ $coalition->catalogItemType }})</span>
                <x-status-badge status="warning" :label="t('messages.nexus.growth.coalitions.status.'.$coalition->status)" />
            </div>

            <div class="grid grid-cols-3 gap-3">
                <x-metric-card :label="t('messages.nexus.growth.coalitions.discount')" :value="$coalition->discountPercent.'%'" />
                <x-metric-card :label="t('messages.nexus.growth.coalitions.members')" :value="count($coalition->members).'/'.$coalition->minParticipants" />
                <x-metric-card :label="t('messages.nexus.growth.coalitions.total_quantity')" :value="$coalition->totalQuantity" />
            </div>

            <div class="mt-4">
                <p class="mb-2 text-sm font-semibold text-nexus-text">{{ t('messages.nexus.growth.coalitions.members') }}</p>
                <ul class="space-y-1 text-sm text-nexus-text-muted">
                    @foreach ($coalition->members as $member)
                        <li>#{{ $member['businessId'] }} — {{ $member['quantity'] }}</li>
                    @endforeach
                </ul>
            </div>

            @if ($coalition->status === 'forming')
                <div class="mt-6 flex flex-wrap gap-3">
                    @if ($businessId === $coalition->organizerBusinessId)
                        <form method="POST" action="{{ route('nexus.growth.coalitions.close', $coalition->id) }}">
                            @csrf
                            <button type="submit" class="rounded-md bg-nexus-cyan/20 px-4 py-2 text-sm font-semibold text-nexus-cyan hover:bg-nexus-cyan/30">
                                {{ t('messages.nexus.growth.coalitions.close') }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('nexus.growth.coalitions.cancel', $coalition->id) }}">
                            @csrf
                            <button type="submit" class="rounded-md border border-nexus-error/40 px-4 py-2 text-sm text-nexus-error hover:bg-nexus-error/10">
                                {{ t('messages.nexus.growth.coalitions.cancel') }}
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('nexus.growth.coalitions.join', $coalition->id) }}" class="flex items-center gap-2">
                            @csrf
                            <input type="number" name="quantity" min="1" value="1" class="w-24 rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text focus:border-nexus-cyan focus:outline-none">
                            <button type="submit" class="rounded-md bg-nexus-cyan/20 px-4 py-2 text-sm font-semibold text-nexus-cyan hover:bg-nexus-cyan/30">
                                {{ t('messages.nexus.growth.coalitions.join') }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('nexus.growth.coalitions.leave', $coalition->id) }}">
                            @csrf
                            <button type="submit" class="rounded-md border border-nexus-border px-4 py-2 text-sm text-nexus-text hover:bg-nexus-surface-1">
                                {{ t('messages.nexus.growth.coalitions.leave') }}
                            </button>
                        </form>
                    @endif
                </div>
            @elseif ($coalition->status === 'negotiating' && $businessId === $coalition->organizerBusinessId)
                <div class="mt-6">
                    <form method="POST" action="{{ route('nexus.growth.coalitions.cancel', $coalition->id) }}">
                        @csrf
                        <button type="submit" class="rounded-md border border-nexus-error/40 px-4 py-2 text-sm text-nexus-error hover:bg-nexus-error/10">
                            {{ t('messages.nexus.growth.coalitions.cancel') }}
                        </button>
                    </form>
                </div>
            @endif
        </x-nexus-panel>
    </div>
@endsection

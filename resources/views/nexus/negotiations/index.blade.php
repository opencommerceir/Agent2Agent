@extends('nexus::layouts.app')

@section('title', t('messages.nexus.negotiation.index.title'))

@section('content')
    <div class="mx-auto max-w-3xl">
        <x-nexus-panel :title="t('messages.nexus.negotiation.index.title')">
            @if (empty($negotiations))
                <p class="text-sm text-nexus-text-muted">{{ t('messages.nexus.negotiation.index.empty') }}</p>
            @else
                <div class="space-y-2">
                    @foreach ($negotiations as $row)
                        @php $negotiation = $row['negotiation']; @endphp
                        <a href="{{ route('nexus.negotiations.show', $negotiation->id) }}" class="flex items-center justify-between rounded-md border border-nexus-border bg-nexus-surface-1/60 px-4 py-3 hover:border-nexus-cyan/40">
                            <div>
                                <p class="text-sm text-nexus-text">
                                    {{ t('messages.nexus.negotiation.index.counterparty') }}:
                                    {{ dashboard_language()->value === 'fa' ? $row['counterpartyNameFa'] : $row['counterpartyNameEn'] }}
                                </p>
                                <p class="text-xs text-nexus-text-muted">
                                    #{{ $negotiation->id }} — {{ t('messages.nexus.negotiation.index.round') }} {{ $negotiation->roundCount }}/{{ $negotiation->maxRounds }}
                                </p>
                            </div>
                            <x-status-badge :status="$negotiation->status === 'accepted' ? 'success' : ($negotiation->status === 'rejected' ? 'error' : ($negotiation->status === 'pending_approval' ? 'warning' : 'idle'))" :label="t('messages.nexus.negotiation.status.'.$negotiation->status)" />
                        </a>
                    @endforeach
                </div>
            @endif
        </x-nexus-panel>
    </div>
@endsection

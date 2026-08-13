@extends('nexus::layouts.app')

@section('title', dashboard_language()->value === 'fa' ? $holding->nameFa : $holding->nameEn)

@section('content')
    @php $isParent = $holding->parentBusinessId === $businessId; @endphp

    <div class="mx-auto max-w-4xl space-y-4">
        <x-nexus-panel>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-nexus-text">{{ dashboard_language()->value === 'fa' ? $holding->nameFa : $holding->nameEn }}</p>
                    <p class="text-xs text-nexus-text-muted">{{ $holding->parentBusinessNameEn }}</p>
                </div>
                <x-status-badge :status="$holding->status === 'active' ? 'success' : 'warning'" :label="$holding->status" />
            </div>
        </x-nexus-panel>

        @if ($isParent)
            <x-nexus-panel :title="t('messages.nexus.holding.invite.submit')">
                <form method="POST" action="{{ route('nexus.holding.invite', $holding->id) }}" class="flex items-end gap-3">
                    @csrf
                    <div class="flex-1">
                        <label class="mb-1 block text-sm text-nexus-text">{{ t('messages.nexus.holding.invite.target_business_id') }}</label>
                        <input type="number" name="target_business_id" required class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text focus:border-nexus-cyan focus:outline-none">
                    </div>
                    <button type="submit" class="rounded-md bg-nexus-cyan/20 px-4 py-2 text-sm font-semibold text-nexus-cyan hover:bg-nexus-cyan/30">
                        {{ t('messages.nexus.holding.invite.submit') }}
                    </button>
                </form>
            </x-nexus-panel>
        @endif

        <x-nexus-panel :title="t('messages.nexus.holding.subsidiaries.title')">
            @if (count($holding->subsidiaries) === 0)
                <p class="text-sm text-nexus-text-muted">{{ t('messages.nexus.holding.subsidiaries.empty') }}</p>
            @else
                <div class="space-y-3">
                    @foreach ($holding->subsidiaries as $subsidiary)
                        <div class="flex items-center justify-between rounded-md border border-nexus-border bg-nexus-surface-1 p-4">
                            <div>
                                <span class="font-mono text-sm text-nexus-text">{{ $subsidiary['nameEn'] }}</span>
                                <x-status-badge :status="$subsidiary['status'] === 'active' ? 'success' : ($subsidiary['status'] === 'invited' ? 'warning' : 'error')" :label="t('messages.nexus.holding.subsidiaries.status.'.$subsidiary['status'])" />
                            </div>
                            <div class="flex gap-2">
                                @if ($isParent && $subsidiary['status'] !== 'removed')
                                    <form method="POST" action="{{ route('nexus.holding.subsidiaries.remove', [$holding->id, $subsidiary['id']]) }}">
                                        @csrf
                                        <button type="submit" class="rounded-md border border-nexus-error/40 px-3 py-1.5 text-sm text-nexus-error hover:bg-nexus-error/10">
                                            {{ t('messages.nexus.holding.subsidiaries.remove') }}
                                        </button>
                                    </form>
                                @elseif (! $isParent && $subsidiary['businessId'] === $businessId && $subsidiary['status'] === 'active')
                                    <form method="POST" action="{{ route('nexus.holding.subsidiaries.leave', $subsidiary['id']) }}">
                                        @csrf
                                        <button type="submit" class="rounded-md border border-nexus-error/40 px-3 py-1.5 text-sm text-nexus-error hover:bg-nexus-error/10">
                                            {{ t('messages.nexus.holding.subsidiaries.leave') }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-nexus-panel>

        <x-nexus-panel :title="t('messages.nexus.holding.dashboard.title')">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-nexus-border text-left text-xs text-nexus-text-muted">
                            <th class="py-2">{{ t('messages.nexus.holding.dashboard.business') }}</th>
                            <th class="py-2">{{ t('messages.nexus.holding.dashboard.credit_balance') }}</th>
                            <th class="py-2">{{ t('messages.nexus.holding.dashboard.active_negotiations') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($dashboard['rows'] as $row)
                            <tr class="border-b border-nexus-border/50">
                                <td class="py-2 text-nexus-text">
                                    {{ $row['nameEn'] }}
                                    @if ($row['isParent'])
                                        <span class="ms-1 text-xs text-nexus-text-muted">({{ t('messages.nexus.holding.subsidiaries.parent_badge') }})</span>
                                    @endif
                                </td>
                                <td class="py-2 text-nexus-text">{{ $row['creditBalance'] ?? '—' }}</td>
                                <td class="py-2 text-nexus-text">{{ $row['activeNegotiations'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                <x-metric-card :label="t('messages.nexus.holding.dashboard.total_credit_balance')" :value="$dashboard['totalCreditBalance']" />
            </div>
        </x-nexus-panel>
    </div>
@endsection

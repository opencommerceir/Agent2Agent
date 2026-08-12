@extends('nexus::layouts.app')

@section('title', t('messages.nexus.growth.referrals.title'))

@section('content')
    <div class="mx-auto max-w-3xl space-y-4">
        <x-nexus-panel :title="t('messages.nexus.growth.referrals.title')">
            <div class="mb-4 flex items-center justify-between">
                <p class="text-sm text-nexus-text-muted">{{ t('messages.nexus.growth.referrals.how_it_works') }}</p>
                <a href="{{ route('nexus.growth.invites.index') }}" class="shrink-0 rounded-md border border-nexus-cyan/40 px-3 py-1.5 text-sm text-nexus-cyan hover:bg-nexus-cyan/10">
                    {{ t('messages.nexus.growth.invites.title') }}
                </a>
            </div>

            <div class="mb-6 flex flex-col gap-2 rounded-md border border-nexus-cyan/40 bg-nexus-cyan/10 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs text-nexus-text-muted">{{ t('messages.nexus.growth.referrals.your_code') }}</p>
                    <p class="font-mono text-lg text-nexus-cyan">{{ $status->code ?? '—' }}</p>
                </div>
                @if ($status->code)
                    <button type="button"
                        onclick="navigator.clipboard.writeText('{{ route('nexus.business.register', ['ref' => $status->code]) }}')"
                        class="rounded-md border border-nexus-cyan/40 px-3 py-1.5 text-sm text-nexus-cyan hover:bg-nexus-cyan/20">
                        {{ t('messages.nexus.growth.referrals.copy_link') }}
                    </button>
                @endif
            </div>

            <div class="mb-6 grid grid-cols-3 gap-3">
                <x-metric-card :label="t('messages.nexus.growth.referrals.tier1_count')" :value="$status->tier1Count" />
                <x-metric-card :label="t('messages.nexus.growth.referrals.tier1_rewarded')" :value="$status->tier1RewardedCount" />
                <x-metric-card :label="t('messages.nexus.growth.referrals.tier2_count')" :value="$status->tier2Count" />
            </div>

            @if (count($status->referrals) === 0)
                <p class="text-sm text-nexus-text-muted">{{ t('messages.nexus.growth.referrals.empty') }}</p>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-nexus-border text-start text-nexus-text-muted">
                            <th class="py-2 text-start">{{ t('messages.nexus.growth.referrals.table_name') }}</th>
                            <th class="py-2 text-start">{{ t('messages.nexus.growth.referrals.table_status') }}</th>
                            <th class="py-2 text-start">{{ t('messages.nexus.growth.referrals.table_date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($status->referrals as $referral)
                            <tr class="border-b border-nexus-border/50">
                                <td class="py-2 text-nexus-text">{{ dashboard_language()->value === 'fa' ? $referral['nameFa'] : $referral['nameEn'] }}</td>
                                <td class="py-2">
                                    <x-status-badge :status="$referral['status'] === 'rewarded' ? 'success' : 'warning'" :label="t('messages.nexus.growth.referrals.status.'.$referral['status'])" />
                                </td>
                                <td class="py-2 text-nexus-text-muted">{{ \Illuminate\Support\Carbon::parse($referral['createdAt'])->format('Y-m-d') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-nexus-panel>
    </div>
@endsection

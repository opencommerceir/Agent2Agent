@extends('layouts.dashboard')

@section('title', t('messages.nexus.admin.compliance.title'))

@section('content')
    <h1 class="mb-2 text-xl font-semibold">{{ t('messages.nexus.admin.compliance.title') }}</h1>
    <p class="mb-6 text-sm text-gray-500">{{ t('messages.nexus.admin.compliance.disclaimer') }}</p>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <p class="text-xs font-medium text-gray-500">{{ t('messages.nexus.admin.compliance.audit_chain') }}</p>
            <p class="mt-1 text-lg font-semibold {{ $overview['auditChain']['intact'] ? 'text-green-700' : 'text-red-700' }}">
                {{ $overview['auditChain']['intact'] ? t('messages.nexus.admin.compliance.intact') : t('messages.nexus.admin.compliance.broken') }}
            </p>
            <p class="text-xs text-gray-500">{{ t('messages.nexus.admin.compliance.entries_checked', ['count' => $overview['auditChain']['checkedCount']]) }}</p>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <p class="text-xs font-medium text-gray-500">{{ t('messages.nexus.admin.compliance.mfa_adoption') }}</p>
            <p class="mt-1 text-lg font-semibold">{{ $overview['mfaAdoptionRate'] }}%</p>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <p class="text-xs font-medium text-gray-500">{{ t('messages.nexus.admin.compliance.suspended_businesses') }}</p>
            <p class="mt-1 text-lg font-semibold">{{ $overview['suspendedBusinessCount'] }} / {{ $overview['totalBusinesses'] }}</p>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <p class="text-xs font-medium text-gray-500">{{ t('messages.nexus.admin.compliance.open_disputes') }}</p>
            <p class="mt-1 text-lg font-semibold">{{ $overview['openDisputeCount'] }}</p>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 sm:col-span-2">
            <p class="mb-2 text-xs font-medium text-gray-500">{{ t('messages.nexus.admin.compliance.sso_providers') }}</p>
            <div class="flex flex-wrap gap-2">
                @foreach ($overview['ssoProviders'] as $provider)
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $provider['isConfigured'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                        {{ $provider['key'] }} — {{ $provider['isConfigured'] ? t('messages.nexus.admin.compliance.live') : t('messages.nexus.admin.compliance.stubbed') }}
                    </span>
                @endforeach
            </div>
        </div>
    </div>

    <div class="mb-6">
        <h2 class="mb-2 text-sm font-semibold text-gray-700">{{ t('messages.nexus.admin.compliance.data_residency_breakdown') }}</h2>
        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">{{ t('messages.nexus.admin.compliance.region') }}</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">{{ t('messages.nexus.admin.compliance.business_count') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach ($overview['dataResidencyBreakdown'] as $region => $count)
                        <tr>
                            <td class="px-4 py-2">{{ $region === 'undeclared' ? t('messages.nexus.admin.compliance.undeclared') : t('messages.nexus.business.dashboard.data_residency.region.'.$region) }}</td>
                            <td class="px-4 py-2">{{ $count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div>
        <h2 class="mb-2 text-sm font-semibold text-gray-700">{{ t('messages.nexus.admin.compliance.checklist_title') }}</h2>
        <div class="space-y-2">
            @foreach ($overview['checklist'] as $item)
                <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm">
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $item['satisfied'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                        {{ $item['satisfied'] ? t('messages.nexus.admin.compliance.satisfied') : t('messages.nexus.admin.compliance.not_satisfied') }}
                    </span>
                    <span>{{ t('messages.nexus.admin.compliance.checklist.'.$item['key']) }}</span>
                </div>
            @endforeach
        </div>
    </div>
@endsection

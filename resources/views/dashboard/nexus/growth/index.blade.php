@extends('layouts.dashboard')

@section('title', t('messages.nexus.admin.growth.title'))

@section('content')
    <h1 class="mb-6 text-xl font-semibold">{{ t('messages.nexus.admin.growth.title') }}</h1>

    <form method="GET" class="mb-6 flex items-end gap-3">
        <div>
            <label class="mb-1 block text-sm font-medium">{{ t('messages.nexus.admin.growth.from') }}</label>
            <input type="date" name="from" value="{{ $from }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">{{ t('messages.nexus.admin.growth.to') }}</label>
            <input type="date" name="to" value="{{ $to }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm">
        </div>
        <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">{{ t('messages.nexus.admin.growth.filter') }}</button>
    </form>

    <div class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-5">
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <p class="text-xs text-gray-500">{{ t('messages.nexus.admin.growth.k_factor') }}</p>
            <p class="text-xl font-semibold">{{ number_format($growth['kFactor'], 2) }}</p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <p class="text-xs text-gray-500">{{ t('messages.nexus.admin.growth.invites_sent') }}</p>
            <p class="text-xl font-semibold">{{ number_format($growth['invitesSent']) }}</p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <p class="text-xs text-gray-500">{{ t('messages.nexus.admin.growth.invites_converted') }}</p>
            <p class="text-xl font-semibold">{{ number_format($growth['invitesConverted']) }}</p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <p class="text-xs text-gray-500">{{ t('messages.nexus.admin.growth.conversion_rate') }}</p>
            <p class="text-xl font-semibold">{{ $growth['conversionRatePercent'] }}%</p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <p class="text-xs text-gray-500">{{ t('messages.nexus.admin.growth.inviting_businesses') }}</p>
            <p class="text-xl font-semibold">{{ number_format($growth['invitingBusinesses']) }}</p>
        </div>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <div>
            <h2 class="mb-2 text-sm font-semibold text-gray-700">{{ t('messages.nexus.admin.growth.cohorts') }}</h2>
            <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">{{ t('messages.nexus.admin.growth.cohort_week') }}</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">{{ t('messages.nexus.admin.growth.cohort_registered') }}</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">{{ t('messages.nexus.admin.growth.cohort_referred') }}</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">{{ t('messages.nexus.admin.growth.cohort_invites_sent') }}</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">{{ t('messages.nexus.admin.growth.cohort_invites_converted') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($growth['cohorts'] as $row)
                            <tr>
                                <td class="px-3 py-2">{{ $row['cohortWeek'] }}</td>
                                <td class="px-3 py-2">{{ $row['businessesRegistered'] }}</td>
                                <td class="px-3 py-2">{{ $row['referredCount'] }}</td>
                                <td class="px-3 py-2">{{ $row['invitesSent'] }}</td>
                                <td class="px-3 py-2">{{ $row['invitesConverted'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-3 py-2 text-gray-400">{{ t('messages.nexus.admin.growth.empty') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <h2 class="mb-2 text-sm font-semibold text-gray-700">{{ t('messages.nexus.admin.growth.variants') }}</h2>
            <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">{{ t('messages.nexus.admin.growth.variant') }}</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">{{ t('messages.nexus.admin.growth.variant_sent') }}</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">{{ t('messages.nexus.admin.growth.variant_converted') }}</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">{{ t('messages.nexus.admin.growth.variant_rate') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($growth['variants'] as $row)
                            <tr>
                                <td class="px-3 py-2">{{ $row['variant'] }}</td>
                                <td class="px-3 py-2">{{ $row['sent'] }}</td>
                                <td class="px-3 py-2">{{ $row['converted'] }}</td>
                                <td class="px-3 py-2">{{ $row['conversionRate'] }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-3 py-2 text-gray-400">{{ t('messages.nexus.admin.growth.empty') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

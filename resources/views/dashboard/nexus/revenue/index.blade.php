@extends('layouts.dashboard')

@section('title', t('messages.nexus.admin.revenue.title'))

@section('content')
    <h1 class="mb-6 text-xl font-semibold">{{ t('messages.nexus.admin.revenue.title') }}</h1>

    <form method="GET" class="mb-6 flex items-end gap-3">
        <div>
            <label class="mb-1 block text-sm font-medium">{{ t('messages.nexus.admin.revenue.from') }}</label>
            <input type="date" name="from" value="{{ $from }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">{{ t('messages.nexus.admin.revenue.to') }}</label>
            <input type="date" name="to" value="{{ $to }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm">
        </div>
        <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">{{ t('messages.nexus.admin.revenue.filter') }}</button>
    </form>

    <div class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <p class="text-xs text-gray-500">{{ t('messages.nexus.admin.revenue.gross') }}</p>
            <p class="text-xl font-semibold">{{ number_format($revenue['grossRevenue']) }}</p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <p class="text-xs text-gray-500">{{ t('messages.nexus.admin.revenue.net') }}</p>
            <p class="text-xl font-semibold">{{ number_format($revenue['netRevenue']) }}</p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <p class="text-xs text-gray-500">{{ t('messages.nexus.admin.revenue.credit_sales') }}</p>
            <p class="text-xl font-semibold">{{ number_format($revenue['creditPackageRevenue']['amount']) }}</p>
            <p class="text-xs text-gray-400">{{ $revenue['creditPackageRevenue']['count'] }} {{ t('messages.nexus.admin.revenue.purchases') }}</p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <p class="text-xs text-gray-500">{{ t('messages.nexus.admin.revenue.escrow_fees') }}</p>
            <p class="text-xl font-semibold">{{ number_format($revenue['escrowFeeRevenue']['amount']) }}</p>
            <p class="text-xs text-gray-400">{{ $revenue['escrowFeeRevenue']['count'] }} {{ t('messages.nexus.admin.revenue.deals') }}</p>
        </div>
    </div>

    <div class="mb-6 rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-600">
        {{ t('messages.nexus.admin.revenue.pending') }}: {{ number_format($revenue['escrowPending']['grossAmount']) }}
        ({{ $revenue['escrowPending']['count'] }}) —
        {{ t('messages.nexus.admin.revenue.credits_deducted') }}: {{ number_format($revenue['creditsDeducted']) }}
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <div>
            <h2 class="mb-2 text-sm font-semibold text-gray-700">{{ t('messages.nexus.admin.revenue.per_business') }}</h2>
            <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">{{ t('messages.nexus.admin.revenue.business') }}</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">{{ t('messages.nexus.admin.revenue.credit_sales') }}</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">{{ t('messages.nexus.admin.revenue.escrow_fees') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($revenue['perBusiness'] as $row)
                            <tr>
                                <td class="px-3 py-2">{{ $row['nameEn'] }}</td>
                                <td class="px-3 py-2">{{ number_format($row['creditPackageRevenue']) }}</td>
                                <td class="px-3 py-2">{{ number_format($row['escrowFeeRevenue']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-3 py-2 text-gray-400">{{ t('messages.nexus.admin.revenue.empty') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <h2 class="mb-2 text-sm font-semibold text-gray-700">{{ t('messages.nexus.admin.revenue.per_industry') }}</h2>
            <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">{{ t('messages.nexus.admin.revenue.industry') }}</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">{{ t('messages.nexus.admin.revenue.credit_sales') }}</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">{{ t('messages.nexus.admin.revenue.escrow_fees') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($revenue['perIndustry'] as $row)
                            <tr>
                                <td class="px-3 py-2">{{ $row['industry'] }}</td>
                                <td class="px-3 py-2">{{ number_format($row['creditPackageRevenue']) }}</td>
                                <td class="px-3 py-2">{{ number_format($row['escrowFeeRevenue']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-3 py-2 text-gray-400">{{ t('messages.nexus.admin.revenue.empty') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-6">
        <h2 class="mb-2 text-sm font-semibold text-gray-700">{{ t('messages.nexus.admin.revenue.per_day') }}</h2>
        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium text-gray-500">{{ t('messages.nexus.admin.revenue.date') }}</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-500">{{ t('messages.nexus.admin.revenue.credit_sales') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($revenue['perDay'] as $row)
                        <tr>
                            <td class="px-3 py-2">{{ $row['date'] }}</td>
                            <td class="px-3 py-2">{{ number_format($row['amount']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="px-3 py-2 text-gray-400">{{ t('messages.nexus.admin.revenue.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

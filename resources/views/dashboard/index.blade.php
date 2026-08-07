@extends('layouts.dashboard')

@section('title', t('messages.dashboard.title'))

@section('content')
    <h1 class="mb-6 text-xl font-semibold">{{ t('messages.dashboard.title') }}</h1>

    @include('dashboard.partials.help', [
        'title' => t('messages.help.dashboard.title'),
        'description' => t('messages.help.dashboard.description'),
    ])

    <form method="GET" action="{{ route('dashboard.index') }}" class="mb-6 max-w-xs">
        <label class="mb-1 block text-sm font-medium">{{ t('messages.settings.select_tenant') }}</label>
        <select name="tenant_id" onchange="this.form.submit()" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
            @foreach ($tenants as $tenant)
                <option value="{{ $tenant->id() }}" @selected($selectedTenantId === $tenant->id())>{{ $tenant->name() }}</option>
            @endforeach
        </select>
    </form>

    @if (! $stats)
        <p class="text-gray-500">{{ t('messages.analytics.select_tenant_prompt') }}</p>
    @else
        {{-- 6 main KPI cards --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            <div class="rounded-lg border border-gray-200 bg-white p-5">
                <div class="text-sm text-gray-500">{{ t('messages.analytics.revenue') }}</div>
                <div class="mt-1 text-xl font-semibold">{{ number_format($stats->totalRevenueCents / 100, 2) }} {{ $stats->currency }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-5">
                <div class="text-sm text-gray-500">{{ t('messages.analytics.total_orders') }}</div>
                <div class="mt-1 text-xl font-semibold">{{ $stats->totalOrders }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-5">
                <div class="text-sm text-gray-500">{{ t('messages.analytics.average_order_value') }}</div>
                <div class="mt-1 text-xl font-semibold">{{ number_format($stats->averageOrderValueCents / 100, 2) }} {{ $stats->currency }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-5">
                <div class="text-sm text-gray-500">{{ t('messages.analytics.total_customers') }}</div>
                <div class="mt-1 text-xl font-semibold">{{ $stats->totalCustomers }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-5">
                <div class="text-sm text-gray-500">{{ t('messages.analytics.conversion_rate') }}</div>
                <div class="mt-1 text-xl font-semibold">{{ number_format($stats->conversionRatePercent, 2) }}%</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-5">
                <div class="text-sm text-gray-500">{{ t('messages.analytics.active_loyalty_accounts') }}</div>
                <div class="mt-1 text-xl font-semibold">{{ $stats->activeLoyaltyAccounts }}</div>
            </div>
        </div>

        {{-- Charts --}}
        <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="rounded-lg border border-gray-200 bg-white p-5">
                <div class="mb-3 text-sm font-medium text-gray-500">{{ t('messages.analytics.revenue_chart') }}</div>
                <canvas id="revenueChart" height="120"></canvas>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-5">
                <div class="mb-3 text-sm font-medium text-gray-500">{{ t('messages.analytics.orders_chart') }}</div>
                <canvas id="ordersChart" height="120"></canvas>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="rounded-lg border border-gray-200 bg-white p-5">
                <div class="mb-3 text-sm font-medium text-gray-500">{{ t('messages.analytics.top_products') }}</div>
                <table class="w-full text-start text-sm">
                    <thead class="text-xs uppercase text-gray-500">
                        <tr>
                            <th class="py-1 text-start">{{ t('messages.products.name') }}</th>
                            <th class="py-1 text-start">{{ t('messages.orders.quantity') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($stats->topProducts as $product)
                            <tr class="border-t border-gray-100">
                                <td class="py-1">{{ $product['name'] ?? $product['productId'] }}</td>
                                <td class="py-1">{{ $product['quantitySold'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="py-2 text-gray-500">{{ t('messages.products.no_products') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-5">
                <div class="mb-3 text-sm font-medium text-gray-500">{{ t('messages.analytics.recent_orders') }}</div>
                <table class="w-full text-start text-sm">
                    <thead class="text-xs uppercase text-gray-500">
                        <tr>
                            <th class="py-1 text-start">{{ t('messages.orders.order_number') }}</th>
                            <th class="py-1 text-start">{{ t('messages.orders.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($stats->recentOrders as $order)
                            <tr class="border-t border-gray-100">
                                <td class="py-1">{{ $order['orderNumber'] }}</td>
                                <td class="py-1">{{ $order['status'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="py-2 text-gray-500">{{ t('messages.orders.no_orders') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <script>
            const revenueChartData = {!! json_encode($revenueChart->toArray()) !!};
            new Chart(document.getElementById('revenueChart').getContext('2d'), {
                type: 'line',
                data: {
                    labels: revenueChartData.labels,
                    datasets: [{
                        label: '{{ t('messages.analytics.revenue') }}',
                        data: revenueChartData.data,
                        borderColor: 'rgb(59, 130, 246)',
                        tension: 0.1,
                    }],
                },
                options: { responsive: true, plugins: { legend: { position: 'top' } } },
            });

            const ordersChartData = {!! json_encode($ordersChart->toArray()) !!};
            new Chart(document.getElementById('ordersChart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: ordersChartData.labels,
                    datasets: [{
                        label: '{{ t('messages.analytics.total_orders') }}',
                        data: ordersChartData.data,
                        backgroundColor: 'rgb(59, 130, 246)',
                    }],
                },
                options: { responsive: true, plugins: { legend: { position: 'top' } } },
            });
        </script>
    @endif

    <div class="mt-8">
        <h2 class="mb-3 text-sm font-medium text-gray-500">{{ t('messages.dashboard.quick_actions') }}</h2>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('dashboard.tenants.create') }}" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">{{ t('messages.tenants.create') }}</a>
            <a href="{{ route('dashboard.agents.create') }}" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">{{ t('messages.agents.create') }}</a>
        </div>
    </div>
@endsection

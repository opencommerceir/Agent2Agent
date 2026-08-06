@if ($stats === null)
    <p class="text-sm text-gray-400">{{ t('showcase.panel.empty') }}</p>
@else
    <div class="grid grid-cols-2 gap-3">
        <div class="rounded-lg bg-gray-50 p-3">
            <p class="text-xs text-gray-400">{{ t('showcase.panel.revenue') }}</p>
            <p class="text-lg font-bold text-gray-800">${{ number_format($stats['totalRevenueCents'] / 100, 2) }}</p>
        </div>
        <div class="rounded-lg bg-gray-50 p-3">
            <p class="text-xs text-gray-400">{{ t('showcase.panel.orders') }}</p>
            <p class="text-lg font-bold text-gray-800">{{ $stats['totalOrders'] }}</p>
        </div>
        <div class="rounded-lg bg-gray-50 p-3">
            <p class="text-xs text-gray-400">{{ t('showcase.panel.aov') }}</p>
            <p class="text-lg font-bold text-gray-800">${{ number_format($stats['averageOrderValueCents'] / 100, 2) }}</p>
        </div>
        <div class="rounded-lg bg-gray-50 p-3">
            <p class="text-xs text-gray-400">{{ t('showcase.panel.customers') }}</p>
            <p class="text-lg font-bold text-gray-800">{{ $stats['totalCustomers'] }}</p>
        </div>
        <div class="rounded-lg bg-gray-50 p-3">
            <p class="text-xs text-gray-400">{{ t('showcase.panel.conversion') }}</p>
            <p class="text-lg font-bold text-gray-800">{{ number_format($stats['conversionRatePercent'], 1) }}%</p>
        </div>
        <div class="rounded-lg bg-gray-50 p-3">
            <p class="text-xs text-gray-400">{{ t('showcase.panel.loyalty') }}</p>
            <p class="text-lg font-bold text-gray-800">{{ $stats['activeLoyaltyAccounts'] }}</p>
        </div>
    </div>

    @if (! empty($stats['topProducts']))
        <div class="mt-4">
            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ t('showcase.panel.top_products') }}</h4>
            <ul class="space-y-1 text-sm text-gray-700">
                @foreach ($stats['topProducts'] as $product)
                    <li class="flex justify-between">
                        <span class="truncate">{{ $product['name'] }}</span>
                        <span class="shrink-0 text-gray-400">{{ $product['quantitySold'] }} {{ t('showcase.panel.sold') }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endif

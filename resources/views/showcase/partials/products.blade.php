<div class="space-y-2">
    @forelse ($products as $product)
        <div class="flex items-center justify-between rounded-lg border border-gray-100 px-3 py-2 text-sm">
            <div class="min-w-0">
                <p class="truncate font-medium text-gray-800">{{ $product['name'] }}</p>
                <p class="text-xs text-gray-400">{{ $product['sku'] }}</p>
            </div>
            <span class="shrink-0 font-semibold text-gray-700">${{ number_format($product['priceAmount'] / 100, 2) }}</span>
        </div>
    @empty
        <p class="text-sm text-gray-400">{{ t('showcase.panel.empty') }}</p>
    @endforelse
</div>

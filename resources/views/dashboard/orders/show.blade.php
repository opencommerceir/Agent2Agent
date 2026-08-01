@extends('layouts.dashboard')

@section('title', t('messages.orders.details'))

@section('content')
    <h1 class="mb-6 text-xl font-semibold">{{ t('messages.orders.details') }}</h1>

    <div class="max-w-2xl space-y-3 rounded-lg border border-gray-200 bg-white p-6 text-sm">
        <div><span class="font-medium">{{ t('messages.orders.order_number') }}:</span> {{ $order->orderNumber }}</div>
        <div><span class="font-medium">{{ t('messages.orders.status') }}:</span> {{ $order->status }}</div>
        <div><span class="font-medium">{{ t('messages.orders.total') }}:</span> {{ number_format($order->totalAmount / 100, 2) }} {{ $order->totalCurrency }}</div>

        <div>
            <div class="mb-1 font-medium">{{ t('messages.orders.items') }}</div>
            <table class="w-full text-start text-sm">
                <thead class="text-xs uppercase text-gray-500">
                    <tr>
                        <th class="py-1 text-start">{{ t('messages.products.name') }}</th>
                        <th class="py-1 text-start">{{ t('messages.orders.quantity') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order->items as $item)
                        <tr class="border-t border-gray-100">
                            <td class="py-1">{{ $item['productName'] ?? $item['productId'] }}</td>
                            <td class="py-1">{{ $item['quantity'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4 flex gap-3">
        <a href="{{ route('dashboard.orders.index', ['tenant_id' => $order->tenantId]) }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">{{ t('messages.common.back') }}</a>

        @if (! in_array($order->status, ['cancelled', 'refunded', 'delivered'], true))
            <form method="POST" action="{{ route('dashboard.orders.cancel', ['orderId' => $order->id, 'tenant_id' => $order->tenantId]) }}">
                @csrf
                <button type="submit" class="rounded-md border border-red-300 px-4 py-2 text-sm text-red-700 hover:bg-red-50">{{ t('messages.orders.cancel') }}</button>
            </form>
        @endif
    </div>
@endsection

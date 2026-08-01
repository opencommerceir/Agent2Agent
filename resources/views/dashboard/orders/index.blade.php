@extends('layouts.dashboard')

@section('title', t('messages.orders.list'))

@section('content')
    <h1 class="mb-6 text-xl font-semibold">{{ t('messages.orders.list') }}</h1>

    <form method="GET" action="{{ route('dashboard.orders.index') }}" class="mb-4 flex flex-wrap gap-4">
        <div>
            <label class="mb-1 block text-sm font-medium">{{ t('messages.settings.select_tenant') }}</label>
            <select name="tenant_id" onchange="this.form.submit()" class="rounded-md border border-gray-300 px-3 py-2 text-sm">
                @foreach ($tenants as $tenant)
                    <option value="{{ $tenant->id() }}" @selected($selectedTenantId === $tenant->id())>{{ $tenant->name() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">{{ t('messages.orders.filter_by_status') }}</label>
            <select name="status" onchange="this.form.submit()" class="rounded-md border border-gray-300 px-3 py-2 text-sm">
                <option value="">{{ t('messages.orders.all_statuses') }}</option>
                @foreach (['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'] as $status)
                    <option value="{{ $status }}" @selected($selectedStatus === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </div>
    </form>

    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
        <table class="w-full text-start text-sm">
            <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3 text-start">{{ t('messages.orders.order_number') }}</th>
                    <th class="px-4 py-3 text-start">{{ t('messages.orders.status') }}</th>
                    <th class="px-4 py-3 text-start">{{ t('messages.orders.total') }}</th>
                    <th class="px-4 py-3 text-start">{{ t('messages.common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    <tr class="border-b border-gray-100 last:border-0">
                        <td class="px-4 py-3">{{ $order['orderNumber'] }}</td>
                        <td class="px-4 py-3">{{ $order['status'] }}</td>
                        <td class="px-4 py-3">{{ number_format($order['totalAmount'] / 100, 2) }} {{ $order['totalCurrency'] }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('dashboard.orders.show', ['orderId' => $order['id'], 'tenant_id' => $selectedTenantId]) }}" class="text-blue-600 hover:underline">{{ t('messages.common.view') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-gray-500">{{ t('messages.orders.no_orders') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

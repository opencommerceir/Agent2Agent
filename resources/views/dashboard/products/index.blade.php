@extends('layouts.dashboard')

@section('title', t('messages.products.list'))

@section('content')
    <h1 class="mb-6 text-xl font-semibold">{{ t('messages.products.list') }}</h1>

    <form method="GET" action="{{ route('dashboard.products.index') }}" class="mb-4 max-w-xs">
        <label class="mb-1 block text-sm font-medium">{{ t('messages.settings.select_tenant') }}</label>
        <select name="tenant_id" onchange="this.form.submit()" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
            @foreach ($tenants as $tenant)
                <option value="{{ $tenant->id() }}" @selected($selectedTenantId === $tenant->id())>{{ $tenant->name() }}</option>
            @endforeach
        </select>
    </form>

    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
        <table class="w-full text-start text-sm">
            <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3 text-start">{{ t('messages.products.name') }}</th>
                    <th class="px-4 py-3 text-start">{{ t('messages.products.sku') }}</th>
                    <th class="px-4 py-3 text-start">{{ t('messages.products.price') }}</th>
                    <th class="px-4 py-3 text-start">{{ t('messages.products.status') }}</th>
                    <th class="px-4 py-3 text-start">{{ t('messages.common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($products as $product)
                    <tr class="border-b border-gray-100 last:border-0">
                        <td class="px-4 py-3">{{ $product['name'] }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $product['sku'] }}</td>
                        <td class="px-4 py-3">{{ number_format($product['priceAmount'] / 100, 2) }} {{ $product['priceCurrency'] }}</td>
                        <td class="px-4 py-3">{{ $product['status'] }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('dashboard.products.show', ['productId' => $product['id'], 'tenant_id' => $selectedTenantId]) }}" class="text-blue-600 hover:underline">{{ t('messages.common.view') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-gray-500">{{ t('messages.products.no_products') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

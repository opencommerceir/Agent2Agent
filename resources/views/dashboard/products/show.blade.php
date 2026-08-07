@extends('layouts.dashboard')

@section('title', t('messages.products.details'))

@section('content')
    <h1 class="mb-6 text-xl font-semibold">{{ t('messages.products.details') }}</h1>

    @include('dashboard.partials.help', [
        'title' => t('messages.help.products_show.title'),
        'description' => t('messages.help.products_show.description'),
    ])

    <div class="max-w-lg space-y-3 rounded-lg border border-gray-200 bg-white p-6 text-sm">
        <div><span class="font-medium">{{ t('messages.products.name') }}:</span> {{ $product->name }}</div>
        <div><span class="font-medium">{{ t('messages.products.sku') }}:</span> {{ $product->sku }}</div>
        <div><span class="font-medium">{{ t('messages.products.price') }}:</span> {{ number_format($product->priceAmount / 100, 2) }} {{ $product->priceCurrency }}</div>
        <div><span class="font-medium">{{ t('messages.products.status') }}:</span> {{ $product->status }}</div>
        <div><span class="font-medium">{{ t('messages.products.description') }}:</span> {{ $product->description ?? '-' }}</div>
    </div>

    <a href="{{ route('dashboard.products.index') }}" class="mt-4 inline-block rounded-md border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">{{ t('messages.common.back') }}</a>
@endsection

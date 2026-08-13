@extends('nexus::layouts.app')

@section('title', t('messages.nexus.catalog.title'))

@section('content')
    <div class="mx-auto max-w-4xl space-y-4">
        @if (session('status'))
            <div class="rounded-md border border-nexus-success/40 bg-nexus-success/10 px-4 py-2 text-sm text-nexus-success">{{ session('status') }}</div>
        @endif

        <x-nexus-panel>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-nexus-text">{{ t('messages.nexus.catalog.title') }}</p>
                    <p class="text-xs text-nexus-text-muted">{{ t('messages.nexus.catalog.description') }}</p>
                </div>
                <a href="{{ route('nexus.business.dashboard') }}" class="text-xs text-nexus-cyan hover:underline">{{ t('messages.nexus.catalog.back') }}</a>
            </div>

            <form method="GET" action="{{ route('nexus.catalog.index') }}" class="mt-4 flex gap-2">
                <input type="text" name="q" value="{{ $query }}" placeholder="{{ t('messages.nexus.catalog.search.placeholder') }}" class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text focus:border-nexus-cyan focus:outline-none">
                <button type="submit" class="rounded-md border border-nexus-border px-4 py-2 text-sm text-nexus-text hover:bg-nexus-surface-1">{{ t('messages.nexus.catalog.search.submit') }}</button>
                @if ($query !== '')
                    <a href="{{ route('nexus.catalog.index') }}" class="rounded-md border border-nexus-border px-4 py-2 text-sm text-nexus-text-muted hover:bg-nexus-surface-1">{{ t('messages.nexus.catalog.search.clear') }}</a>
                @endif
            </form>
        </x-nexus-panel>

        <x-nexus-panel :title="t('messages.nexus.catalog.products.title')">
            <div class="mb-3 flex justify-end">
                <a href="{{ route('nexus.catalog.products.create') }}" class="rounded-md bg-nexus-cyan/20 px-4 py-1.5 text-sm font-semibold text-nexus-cyan hover:bg-nexus-cyan/30">
                    {{ t('messages.nexus.catalog.products.add') }}
                </a>
            </div>

            @if (empty($products))
                <p class="text-sm text-nexus-text-muted">{{ t('messages.nexus.catalog.products.empty') }}</p>
            @else
                <div class="space-y-2">
                    @foreach ($products as $product)
                        <a href="{{ route('nexus.catalog.products.edit', $product->id) }}" class="flex items-center justify-between rounded-md border border-nexus-border bg-nexus-surface-1/60 px-4 py-3 hover:border-nexus-cyan/40">
                            <div>
                                <p class="text-sm text-nexus-text">{{ dashboard_language()->value === 'fa' ? $product->nameFa : $product->nameEn }}</p>
                                <p class="text-xs text-nexus-text-muted">
                                    {{ number_format($product->priceAmount) }} {{ $product->priceCurrency }} — {{ t('messages.nexus.catalog.fields.stock_quantity') }}: {{ $product->stockQuantity }}
                                </p>
                            </div>
                            <x-status-badge :status="$product->verificationStatus === 'verified' ? 'success' : ($product->verificationStatus === 'rejected' ? 'error' : 'warning')" :label="t('messages.nexus.catalog.verification_status.'.$product->verificationStatus)" />
                        </a>
                    @endforeach
                </div>
            @endif
        </x-nexus-panel>

        <x-nexus-panel :title="t('messages.nexus.catalog.services.title')">
            <div class="mb-3 flex justify-end">
                <a href="{{ route('nexus.catalog.services.create') }}" class="rounded-md bg-nexus-cyan/20 px-4 py-1.5 text-sm font-semibold text-nexus-cyan hover:bg-nexus-cyan/30">
                    {{ t('messages.nexus.catalog.services.add') }}
                </a>
            </div>

            @if (empty($services))
                <p class="text-sm text-nexus-text-muted">{{ t('messages.nexus.catalog.services.empty') }}</p>
            @else
                <div class="space-y-2">
                    @foreach ($services as $service)
                        <a href="{{ route('nexus.catalog.services.edit', $service->id) }}" class="flex items-center justify-between rounded-md border border-nexus-border bg-nexus-surface-1/60 px-4 py-3 hover:border-nexus-cyan/40">
                            <div>
                                <p class="text-sm text-nexus-text">{{ dashboard_language()->value === 'fa' ? $service->nameFa : $service->nameEn }}</p>
                                <p class="text-xs text-nexus-text-muted">
                                    {{ number_format($service->priceAmount) }} {{ $service->priceCurrency }}
                                    @if ($service->durationMinutes)
                                        — {{ t('messages.nexus.catalog.fields.duration_minutes') }}: {{ $service->durationMinutes }}
                                    @endif
                                </p>
                            </div>
                            <x-status-badge :status="$service->verificationStatus === 'verified' ? 'success' : ($service->verificationStatus === 'rejected' ? 'error' : 'warning')" :label="t('messages.nexus.catalog.verification_status.'.$service->verificationStatus)" />
                        </a>
                    @endforeach
                </div>
            @endif
        </x-nexus-panel>
    </div>
@endsection

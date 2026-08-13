@extends('nexus::layouts.app')

@section('title', t('messages.nexus.automation.create_new'))

@section('content')
    <div class="mx-auto max-w-2xl space-y-4">
        <x-nexus-panel :title="t('messages.nexus.automation.create_new')">
            <form method="GET" class="mb-4">
                <label class="mb-1 block text-xs text-nexus-text-muted">{{ t('messages.nexus.automation.rule_type') }}</label>
                <select name="type" class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-1.5 text-sm text-nexus-text" onchange="this.form.submit()">
                    @foreach (['recurring_order', 'inventory_alert', 'price_alert'] as $option)
                        <option value="{{ $option }}" @selected($type === $option)>{{ t('messages.nexus.automation.type.'.$option) }}</option>
                    @endforeach
                </select>
            </form>

            <form method="POST" action="{{ route('nexus.automation.store') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="type" value="{{ $type }}">

                @if ($type === 'recurring_order')
                    <div>
                        <label class="mb-1 block text-xs text-nexus-text-muted">{{ t('messages.nexus.automation.fields.counterparty_business_id') }}</label>
                        <input type="number" name="counterparty_business_id" required class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-1.5 text-sm text-nexus-text">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-nexus-text-muted">{{ t('messages.nexus.automation.fields.catalog_item_type') }}</label>
                        <select name="catalog_item_type" class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-1.5 text-sm text-nexus-text">
                            @foreach ($catalogItemTypes as $case)
                                <option value="{{ $case->value }}">{{ $case->value }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-nexus-text-muted">{{ t('messages.nexus.automation.fields.catalog_item_id') }}</label>
                        <input type="number" name="catalog_item_id" required class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-1.5 text-sm text-nexus-text">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-xs text-nexus-text-muted">{{ t('messages.nexus.automation.fields.price_amount') }}</label>
                            <input type="number" name="price_amount" required min="1" class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-1.5 text-sm text-nexus-text">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-nexus-text-muted">{{ t('messages.nexus.automation.fields.price_currency') }}</label>
                            <input type="text" name="price_currency" required maxlength="3" value="IRT" class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-1.5 text-sm text-nexus-text">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-xs text-nexus-text-muted">{{ t('messages.nexus.automation.fields.quantity') }}</label>
                            <input type="number" name="quantity" required min="1" value="1" class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-1.5 text-sm text-nexus-text">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-nexus-text-muted">{{ t('messages.nexus.automation.fields.interval_days') }}</label>
                            <input type="number" name="interval_days" required min="1" value="30" class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-1.5 text-sm text-nexus-text">
                        </div>
                    </div>
                @elseif ($type === 'inventory_alert')
                    <div>
                        <label class="mb-1 block text-xs text-nexus-text-muted">{{ t('messages.nexus.automation.fields.product_id') }}</label>
                        <input type="number" name="product_id" required class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-1.5 text-sm text-nexus-text">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-nexus-text-muted">{{ t('messages.nexus.automation.fields.threshold_quantity') }}</label>
                        <input type="number" name="threshold_quantity" required min="0" class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-1.5 text-sm text-nexus-text">
                    </div>
                @elseif ($type === 'price_alert')
                    <div>
                        <label class="mb-1 block text-xs text-nexus-text-muted">{{ t('messages.nexus.automation.fields.catalog_item_type') }}</label>
                        <select name="catalog_item_type" class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-1.5 text-sm text-nexus-text">
                            @foreach ($catalogItemTypes as $case)
                                <option value="{{ $case->value }}">{{ $case->value }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-nexus-text-muted">{{ t('messages.nexus.automation.fields.catalog_item_id') }}</label>
                        <input type="number" name="catalog_item_id" required class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-1.5 text-sm text-nexus-text">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-nexus-text-muted">{{ t('messages.nexus.automation.fields.target_price_amount') }}</label>
                        <input type="number" name="target_price_amount" required min="0" class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-1.5 text-sm text-nexus-text">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-nexus-text-muted">{{ t('messages.nexus.automation.fields.direction') }}</label>
                        <select name="direction" class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-1.5 text-sm text-nexus-text">
                            @foreach ($directions as $case)
                                <option value="{{ $case->value }}">{{ t('messages.nexus.automation.direction.'.$case->value) }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <button type="submit" class="rounded-md bg-nexus-cyan/20 px-4 py-1.5 text-sm font-semibold text-nexus-cyan hover:bg-nexus-cyan/30">
                    {{ t('messages.nexus.automation.create_new') }}
                </button>
            </form>
        </x-nexus-panel>
    </div>
@endsection

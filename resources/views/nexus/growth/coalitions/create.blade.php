@extends('nexus::layouts.app')

@section('title', t('messages.nexus.growth.coalitions.create_new'))

@section('content')
    <div class="mx-auto max-w-2xl">
        <x-nexus-panel :title="t('messages.nexus.growth.coalitions.create_new')">
            @if ($errors->any())
                <div class="mb-4 rounded-md border border-nexus-error/40 bg-nexus-error/10 px-4 py-3 text-sm text-nexus-error">
                    <ul class="list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('nexus.growth.coalitions.store') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="mb-1 block text-sm text-nexus-text">{{ t('messages.nexus.growth.coalitions.form.target_business_id') }}</label>
                    <input type="number" name="target_business_id" required class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text focus:border-nexus-cyan focus:outline-none">
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm text-nexus-text">{{ t('messages.nexus.growth.coalitions.form.catalog_item_type') }}</label>
                        <select name="catalog_item_type" required class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text focus:border-nexus-cyan focus:outline-none">
                            @foreach ($catalogItemTypes as $type)
                                <option value="{{ $type->value }}">{{ $type->value }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-nexus-text">{{ t('messages.nexus.growth.coalitions.form.catalog_item_id') }}</label>
                        <input type="number" name="catalog_item_id" required class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text focus:border-nexus-cyan focus:outline-none">
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm text-nexus-text">{{ t('messages.nexus.growth.coalitions.form.unit_price_amount') }}</label>
                        <input type="number" name="unit_price_amount" required class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text focus:border-nexus-cyan focus:outline-none">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-nexus-text">{{ t('messages.nexus.growth.coalitions.form.unit_price_currency') }}</label>
                        <input type="text" name="unit_price_currency" value="IRT" maxlength="3" required class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text focus:border-nexus-cyan focus:outline-none">
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-sm text-nexus-text">{{ t('messages.nexus.growth.coalitions.form.min_participants') }}</label>
                        <input type="number" name="min_participants" min="2" value="3" required class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text focus:border-nexus-cyan focus:outline-none">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-nexus-text">{{ t('messages.nexus.growth.coalitions.form.discount_percent') }}</label>
                        <input type="number" step="0.1" name="discount_percent" min="0" max="100" required class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text focus:border-nexus-cyan focus:outline-none">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-nexus-text">{{ t('messages.nexus.growth.coalitions.form.quantity') }}</label>
                        <input type="number" name="quantity" min="1" value="1" required class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text focus:border-nexus-cyan focus:outline-none">
                    </div>
                </div>

                <button type="submit" class="w-full rounded-md bg-nexus-cyan/20 px-4 py-2 text-sm font-semibold text-nexus-cyan hover:bg-nexus-cyan/30">
                    {{ t('messages.nexus.growth.coalitions.form.submit') }}
                </button>
            </form>
        </x-nexus-panel>
    </div>
@endsection

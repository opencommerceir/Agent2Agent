@extends('layouts.dashboard')

@section('title', t('messages.nexus.admin.margin_settings.title'))

@section('content')
    <h1 class="mb-6 text-xl font-semibold">{{ t('messages.nexus.admin.margin_settings.title') }}</h1>

    @if (session('status'))
        <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-700">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('dashboard.nexus.margin-settings.update') }}" class="max-w-lg space-y-4 rounded-lg border border-gray-200 bg-white p-6">
        @csrf
        @method('PUT')

        <div>
            <label class="mb-1 block text-sm font-medium">{{ t('messages.nexus.admin.margin_settings.llm_cost_markup_percent') }}</label>
            <input type="number" step="0.1" min="0" name="llm_cost_markup_percent" value="{{ old('llm_cost_markup_percent', $llmCostMarkupPercent) }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">{{ t('messages.nexus.admin.margin_settings.transaction_fee_percent') }}</label>
            <input type="number" step="0.1" min="0" name="transaction_fee_percent" value="{{ old('transaction_fee_percent', $transactionFeePercent) }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">{{ t('messages.nexus.admin.margin_settings.subscription_markup_percent') }}</label>
            <input type="number" step="0.1" min="0" name="subscription_markup_percent" value="{{ old('subscription_markup_percent', $subscriptionMarkupPercent) }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">{{ t('messages.nexus.admin.margin_settings.negotiation_fee_percent') }}</label>
            <input type="number" step="0.1" min="0" name="negotiation_fee_percent" value="{{ old('negotiation_fee_percent', $negotiationFeePercent) }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
        </div>

        <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">{{ t('messages.common.save') }}</button>
    </form>
@endsection

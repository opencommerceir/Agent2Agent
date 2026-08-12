@extends('layouts.dashboard')

@section('title', t('messages.nexus.admin.llm_settings.title'))

@section('content')
    <h1 class="mb-6 text-xl font-semibold">{{ t('messages.nexus.admin.llm_settings.title') }}</h1>

    @if ($isOverBudget)
        <div class="mb-4 rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            {{ t('messages.nexus.admin.llm_settings.over_budget_banner') }}
        </div>
    @endif

    <div x-data="{ csrfToken: '{{ csrf_token() }}' }">
        <form method="POST" action="{{ route('dashboard.nexus.llm-settings.update') }}" class="max-w-2xl space-y-6 rounded-lg border border-gray-200 bg-white p-6">
            @csrf
            @method('PUT')

            <div>
                <h2 class="mb-3 text-sm font-semibold text-gray-700">{{ t('messages.nexus.admin.llm_settings.feature_providers') }}</h2>
                <div class="space-y-3">
                    @foreach ($features as $feature)
                        <div x-data="{ provider: '{{ old('feature_provider.'.$feature->value, $featureProviders[$feature->value]) }}', testing: false, result: null }" class="flex items-center gap-2">
                            <label class="w-32 shrink-0 text-sm font-medium">{{ t('messages.nexus.admin.llm_settings.feature_'.$feature->value) }}</label>

                            <select name="feature_provider[{{ $feature->value }}]" x-model="provider" class="flex-1 rounded-md border border-gray-300 px-3 py-2 text-sm">
                                @foreach ($registeredProviders as $providerId)
                                    <option value="{{ $providerId }}">{{ $providerId }}</option>
                                @endforeach
                            </select>

                            <button
                                type="button"
                                class="shrink-0 rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium hover:bg-gray-100"
                                :disabled="testing"
                                @click="
                                    testing = true; result = null;
                                    fetch('{{ route('dashboard.nexus.llm-settings.test-connection') }}', {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                                        body: JSON.stringify({ provider: provider }),
                                    }).then(r => r.json()).then(data => { testing = false; result = data; });
                                "
                            >
                                <span x-show="!testing">{{ t('messages.nexus.admin.llm_settings.test_connection') }}</span>
                                <span x-show="testing">…</span>
                            </button>

                            <span x-show="result && result.success" class="shrink-0 text-xs font-medium text-green-700">{{ t('messages.nexus.admin.llm_settings.test_connection_success') }}</span>
                            <span x-show="result && !result.success" class="shrink-0 text-xs font-medium text-red-700">{{ t('messages.nexus.admin.llm_settings.test_connection_failure') }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">{{ t('messages.nexus.admin.llm_settings.fallback_chain') }}</label>
                <input type="text" name="fallback_chain" value="{{ old('fallback_chain', implode(',', $fallbackChain)) }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
            </div>

            <div>
                <h2 class="mb-3 text-sm font-semibold text-gray-700">{{ t('messages.nexus.admin.llm_settings.cost_control') }}</h2>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium">{{ t('messages.nexus.admin.llm_settings.daily_budget_per_agent_irt') }}</label>
                        <input type="number" step="1" min="0" name="daily_budget_per_agent_irt" value="{{ old('daily_budget_per_agent_irt', $dailyBudgetPerAgentIrt) }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">{{ t('messages.nexus.admin.llm_settings.monthly_budget_per_business_irt') }}</label>
                        <input type="number" step="1" min="0" name="monthly_budget_per_business_irt" value="{{ old('monthly_budget_per_business_irt', $monthlyBudgetPerBusinessIrt) }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                    </div>
                </div>
            </div>

            <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">{{ t('messages.common.save') }}</button>
        </form>
    </div>
@endsection

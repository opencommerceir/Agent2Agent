@extends('layouts.dashboard')

@section('title', t('messages.analytics.title'))

@section('content')
    <h1 class="mb-6 text-xl font-semibold">{{ t('messages.analytics.title') }}</h1>

    <form method="GET" action="{{ route('dashboard.analytics.index') }}" class="mb-6 grid max-w-3xl grid-cols-2 gap-4 rounded-lg border border-gray-200 bg-white p-6 sm:grid-cols-3 lg:grid-cols-5">
        <div>
            <label class="mb-1 block text-sm font-medium">{{ t('messages.settings.select_tenant') }}</label>
            <select name="tenant_id" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                @foreach ($tenants as $tenant)
                    <option value="{{ $tenant->id() }}" @selected($selectedTenantId === $tenant->id())>{{ $tenant->name() }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">{{ t('messages.analytics.kpi_type') }}</label>
            <select name="kpi_type" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                @foreach ($kpiTypes as $type)
                    <option value="{{ $type }}" @selected(request('kpi_type') === $type)>{{ $type }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">{{ t('messages.analytics.time_period') }}</label>
            <select name="time_period" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                @foreach (['daily', 'weekly', 'monthly', 'yearly'] as $period)
                    <option value="{{ $period }}" @selected(request('time_period', 'monthly') === $period)>{{ $period }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">{{ t('messages.analytics.start_date') }}</label>
            <input type="date" name="start_date" value="{{ $startDate }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">{{ t('messages.analytics.end_date') }}</label>
            <input type="date" name="end_date" value="{{ $endDate }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
        </div>

        <div class="col-span-2 flex items-end sm:col-span-3 lg:col-span-5">
            <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">{{ t('messages.analytics.calculate') }}</button>
        </div>
    </form>

    @if ($result)
        <div class="mb-6 max-w-md rounded-lg border border-gray-200 bg-white p-6">
            <div class="text-sm text-gray-500">{{ $result->kpiType }} ({{ $result->periodStart }} &rarr; {{ $result->periodEnd }})</div>
            <div class="mt-1 text-2xl font-semibold">
                @if ($result->unit === 'PCT')
                    {{ number_format($result->amount / 100, 2) }}%
                @elseif (in_array($result->unit, ['CNT', 'PTS'], true))
                    {{ $result->amount }}
                @elseif ($result->unit === 'LST')
                    {{ count($result->metadata['products'] ?? []) }} {{ t('messages.analytics.top_products') }}
                @else
                    {{ number_format($result->amount / 100, 2) }} {{ $result->unit }}
                @endif
            </div>
        </div>
    @endif

    <div class="flex gap-3">
        <a href="{{ route('dashboard.analytics.export.csv', ['tenant_id' => $selectedTenantId, 'start_date' => $startDate, 'end_date' => $endDate]) }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">{{ t('messages.analytics.export_csv') }}</a>
        <a href="{{ route('dashboard.analytics.export.pdf', ['tenant_id' => $selectedTenantId, 'start_date' => $startDate, 'end_date' => $endDate]) }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">{{ t('messages.analytics.export_pdf') }}</a>
    </div>
@endsection

@extends('layouts.dashboard')

@section('title', t('messages.performance.title'))

@section('content')
    <h1 class="mb-6 text-xl font-semibold">{{ t('messages.performance.title') }}</h1>

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <div class="text-sm text-gray-500">{{ t('messages.performance.average_response_time') }}</div>
            <div class="mt-1 text-2xl font-semibold">{{ $averageResponseTime }}ms</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <div class="text-sm text-gray-500">{{ t('messages.performance.cache_hit_rate') }}</div>
            <div class="mt-1 text-2xl font-semibold">{{ $cacheHitRate }}%</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <div class="text-sm text-gray-500">{{ t('messages.performance.request_count') }}</div>
            <div class="mt-1 text-2xl font-semibold">{{ $requestCount }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <div class="text-sm text-gray-500">{{ t('messages.performance.memory_usage') }}</div>
            <div class="mt-1 text-2xl font-semibold">{{ $memoryUsageMb }} MB</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <div class="text-sm text-gray-500">{{ t('messages.performance.database_connections') }}</div>
            <div class="mt-1 text-2xl font-semibold">{{ $databaseConnections }}</div>
        </div>
    </div>

    <div class="mb-6 rounded-lg border border-gray-200 bg-white p-6">
        <h2 class="mb-4 text-lg font-medium">{{ t('messages.performance.slow_queries') }}</h2>

        @if (count($slowQueries) === 0)
            <p class="text-sm text-gray-500">{{ t('messages.performance.no_slow_queries') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-gray-500">
                            <th class="py-2 pr-4">SQL</th>
                            <th class="py-2 pr-4">ms</th>
                            <th class="py-2">at</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($slowQueries as $query)
                            <tr class="border-b border-gray-100">
                                <td class="py-2 pr-4 font-mono text-xs">{{ $query['query'] }}</td>
                                <td class="py-2 pr-4">{{ $query['time_ms'] }}</td>
                                <td class="py-2">{{ $query['at'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="rounded-lg border border-gray-200 bg-white p-6">
        <h2 class="mb-4 text-lg font-medium">{{ t('messages.performance.optimization_suggestions') }}</h2>

        @if (count($optimizationSuggestions) === 0)
            <p class="text-sm text-gray-500">{{ t('messages.performance.no_suggestions') }}</p>
        @else
            <ul class="list-inside list-disc space-y-1 text-sm">
                @foreach ($optimizationSuggestions as $suggestion)
                    <li>{{ $suggestion }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection

@extends('layouts.dashboard')

@section('title', t('messages.nexus.admin.audit.title'))

@section('content')
    <h1 class="mb-6 text-xl font-semibold">{{ t('messages.nexus.admin.audit.title') }}</h1>

    @if (session('status'))
        <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-700">{{ session('status') }}</div>
    @endif

    <div class="mb-6 flex items-center justify-between">
        <p class="text-sm text-gray-600">{{ t('messages.nexus.admin.audit.total_entries', ['count' => $totalCount]) }}</p>

        <form method="POST" action="{{ route('dashboard.nexus.audit.verify') }}">
            @csrf
            <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                {{ t('messages.nexus.admin.audit.verify_chain') }}
            </button>
        </form>
    </div>

    @if (empty($entries))
        <p class="text-sm text-gray-500">{{ t('messages.nexus.admin.audit.empty') }}</p>
    @else
        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">{{ t('messages.nexus.admin.audit.sequence') }}</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">{{ t('messages.nexus.admin.audit.capability') }}</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">{{ t('messages.nexus.admin.audit.business') }}</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">{{ t('messages.nexus.admin.audit.agent') }}</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">{{ t('messages.nexus.admin.audit.status') }}</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">{{ t('messages.nexus.admin.audit.duration') }}</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">{{ t('messages.nexus.admin.audit.hash') }}</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">{{ t('messages.nexus.admin.audit.at') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach ($entries as $entry)
                        <tr>
                            <td class="px-4 py-2 font-mono text-xs">#{{ $entry->sequence() }}</td>
                            <td class="px-4 py-2">{{ $entry->capabilityName() }}</td>
                            <td class="px-4 py-2">{{ $entry->businessId() ?? '—' }}</td>
                            <td class="px-4 py-2">{{ $entry->coreAgentId() ?? '—' }}</td>
                            <td class="px-4 py-2">
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-xs font-medium',
                                    'bg-green-100 text-green-700' => $entry->status()->value === 'success',
                                    'bg-yellow-100 text-yellow-700' => $entry->status()->value === 'denied',
                                    'bg-red-100 text-red-700' => $entry->status()->value === 'error',
                                ])>{{ $entry->status()->value }}</span>
                            </td>
                            <td class="px-4 py-2 font-mono text-xs">{{ $entry->executionTimeMs() }}ms</td>
                            <td class="px-4 py-2 font-mono text-xs" title="{{ $entry->entryHash() }}">{{ substr($entry->entryHash(), 0, 12) }}…</td>
                            <td class="px-4 py-2 text-xs text-gray-500">{{ $entry->createdAt()->format('Y-m-d H:i:s') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection

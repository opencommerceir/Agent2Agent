@extends('layouts.dashboard')

@section('title', t('messages.tenants.list'))

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold">{{ t('messages.tenants.list') }}</h1>
        <a href="{{ route('dashboard.tenants.create') }}" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">{{ t('messages.tenants.create') }}</a>
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
        <table class="w-full text-start text-sm">
            <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3 text-start">{{ t('messages.tenants.name') }}</th>
                    <th class="px-4 py-3 text-start">{{ t('messages.tenants.domain') }}</th>
                    <th class="px-4 py-3 text-start">{{ t('messages.tenants.status') }}</th>
                    <th class="px-4 py-3 text-start">{{ t('messages.common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tenants as $tenant)
                    <tr class="border-b border-gray-100 last:border-0">
                        <td class="px-4 py-3">{{ $tenant->name() }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $tenant->slug() }}</td>
                        <td class="px-4 py-3">{{ $tenant->status()->value }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('dashboard.tenants.edit', $tenant->id()) }}" class="text-blue-600 hover:underline">{{ t('messages.common.edit') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-gray-500">{{ t('messages.tenants.no_tenants') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

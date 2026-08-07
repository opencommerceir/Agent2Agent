@extends('layouts.dashboard')

@section('title', t('messages.agents.list'))

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold">{{ t('messages.agents.list') }}</h1>
        <a href="{{ route('dashboard.agents.create') }}" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">{{ t('messages.agents.create') }}</a>
    </div>

    @include('dashboard.partials.help', [
        'title' => t('messages.help.agents_index.title'),
        'description' => t('messages.help.agents_index.description'),
        'example' => t('messages.help.agents_index.example'),
    ])

    <form method="GET" action="{{ route('dashboard.agents.index') }}" class="mb-4 max-w-xs">
        <label class="mb-1 block text-sm font-medium">{{ t('messages.agents.filter_by_tenant') }}</label>
        <select name="tenant_id" onchange="this.form.submit()" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
            <option value="">{{ t('messages.agents.all_tenants') }}</option>
            @foreach ($tenants as $tenant)
                <option value="{{ $tenant->id() }}" @selected($selectedTenantId === $tenant->id())>{{ $tenant->name() }}</option>
            @endforeach
        </select>
    </form>

    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
        <table class="w-full text-start text-sm">
            <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3 text-start">{{ t('messages.tenants.name') }}</th>
                    <th class="px-4 py-3 text-start">{{ t('messages.agents.type') }}</th>
                    <th class="px-4 py-3 text-start">{{ t('messages.agents.status') }}</th>
                    <th class="px-4 py-3 text-start">{{ t('messages.common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($agents as $agent)
                    <tr class="border-b border-gray-100 last:border-0">
                        <td class="px-4 py-3">{{ $agent->name() }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $agent->type()->value }}</td>
                        <td class="px-4 py-3">{{ $agent->status()->value }}</td>
                        <td class="px-4 py-3 space-x-2 rtl:space-x-reverse">
                            <a href="{{ route('dashboard.agents.edit', $agent->id()) }}" class="text-blue-600 hover:underline">{{ t('messages.common.edit') }}</a>
                            @if ($agent->isActive())
                                <form method="POST" action="{{ route('dashboard.agents.suspend', $agent->id()) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-amber-600 hover:underline">{{ t('messages.agents.suspend') }}</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('dashboard.agents.activate', $agent->id()) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-green-600 hover:underline">{{ t('messages.agents.activate') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-gray-500">{{ t('messages.agents.no_agents') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

@extends('layouts.dashboard')

@section('title', t('messages.dashboard.title'))

@section('content')
    <h1 class="mb-6 text-xl font-semibold">{{ t('messages.dashboard.title') }}</h1>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-lg border border-gray-200 bg-white p-5">
            <div class="text-sm text-gray-500">{{ t('messages.dashboard.total_tenants') }}</div>
            <div class="mt-1 text-2xl font-semibold">{{ $totalTenants }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-5">
            <div class="text-sm text-gray-500">{{ t('messages.dashboard.total_agents') }}</div>
            <div class="mt-1 text-2xl font-semibold">{{ $totalAgents }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-5">
            <div class="text-sm text-gray-500">{{ t('messages.dashboard.total_orders') }}</div>
            <div class="mt-1 text-2xl font-semibold">{{ $totalOrders }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-5">
            <div class="text-sm text-gray-500">{{ t('messages.dashboard.total_notifications') }}</div>
            <div class="mt-1 text-2xl font-semibold">{{ $totalNotifications }}</div>
        </div>
    </div>

    <div class="mt-8">
        <h2 class="mb-3 text-sm font-medium text-gray-500">{{ t('messages.dashboard.quick_actions') }}</h2>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('dashboard.tenants.create') }}" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">{{ t('messages.tenants.create') }}</a>
            <a href="{{ route('dashboard.agents.create') }}" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">{{ t('messages.agents.create') }}</a>
        </div>
    </div>
@endsection

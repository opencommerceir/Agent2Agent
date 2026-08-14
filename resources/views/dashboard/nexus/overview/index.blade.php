@extends('layouts.dashboard')

@section('title', t('messages.nexus.admin.overview.title'))

@php
    $actionCards = [
        ['label' => 'pending_business_verifications', 'value' => $overview['pendingBusinessVerifications'], 'route' => 'dashboard.nexus.verification.index'],
        ['label' => 'pending_listing_verifications', 'value' => $overview['pendingListingVerifications'], 'route' => 'dashboard.nexus.verification.index'],
        ['label' => 'open_disputes', 'value' => $overview['openDisputes'], 'route' => 'dashboard.nexus.disputes.index'],
        ['label' => 'disputed_escrows', 'value' => $overview['disputedEscrows'], 'route' => 'dashboard.nexus.escrows.index'],
        ['label' => 'suspended_businesses', 'value' => $overview['suspendedBusinesses'], 'route' => 'dashboard.nexus.fraud.index'],
        ['label' => 'pending_appeals', 'value' => $overview['pendingAppeals'], 'route' => 'dashboard.nexus.fraud.index'],
    ];
@endphp

@section('content')
    <h1 class="mb-1 text-xl font-semibold">{{ t('messages.nexus.admin.overview.title') }}</h1>
    <p class="mb-6 text-sm text-gray-500">{{ t('messages.nexus.admin.overview.subtitle') }}</p>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($actionCards as $card)
            <a
                href="{{ route($card['route']) }}"
                class="block rounded-lg border p-4 transition hover:shadow-sm {{ $card['value'] > 0 ? 'border-amber-300 bg-amber-50' : 'border-gray-200 bg-white' }}"
            >
                <div class="text-3xl font-semibold {{ $card['value'] > 0 ? 'text-amber-700' : 'text-gray-400' }}">{{ $card['value'] }}</div>
                <div class="mt-1 text-sm text-gray-600">{{ t('messages.nexus.admin.overview.'.$card['label']) }}</div>
            </a>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <a href="{{ route('dashboard.nexus.negotiations.index') }}" class="block rounded-lg border border-gray-200 bg-white p-4 transition hover:shadow-sm">
            <div class="text-3xl font-semibold text-blue-600">{{ $overview['activeNegotiations'] }}</div>
            <div class="mt-1 text-sm text-gray-600">{{ t('messages.nexus.admin.overview.active_negotiations') }}</div>
        </a>
        <a href="{{ route('dashboard.nexus.revenue.index') }}" class="block rounded-lg border border-gray-200 bg-white p-4 transition hover:shadow-sm">
            <div class="text-3xl font-semibold text-green-600">{{ number_format($overview['grossRevenue']) }}</div>
            <div class="mt-1 text-sm text-gray-600">{{ t('messages.nexus.admin.overview.gross_revenue') }}</div>
        </a>
    </div>
@endsection

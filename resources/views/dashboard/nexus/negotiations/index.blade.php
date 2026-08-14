@extends('layouts.dashboard')

@section('title', t('messages.nexus.admin.negotiations.title'))

@php
    $statusClasses = [
        'proposed' => 'bg-blue-50 text-blue-700',
        'countered' => 'bg-blue-50 text-blue-700',
        'pending_approval' => 'bg-amber-50 text-amber-700',
        'accepted' => 'bg-green-50 text-green-700',
        'rejected' => 'bg-red-50 text-red-700',
        'expired' => 'bg-gray-100 text-gray-500',
    ];
@endphp

@section('content')
    <h1 class="mb-1 text-xl font-semibold">{{ t('messages.nexus.admin.negotiations.title') }}</h1>
    <p class="mb-6 text-sm text-gray-500">{{ t('messages.nexus.admin.negotiations.subtitle') }}</p>

    @if (empty($rows))
        <p class="text-sm text-gray-500">{{ t('messages.nexus.admin.negotiations.empty') }}</p>
    @else
        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-start">#</th>
                        <th class="px-4 py-3 text-start">{{ t('messages.nexus.admin.negotiations.initiator') }}</th>
                        <th class="px-4 py-3 text-start">{{ t('messages.nexus.admin.negotiations.counterparty') }}</th>
                        <th class="px-4 py-3 text-start">{{ t('messages.nexus.admin.negotiations.item') }}</th>
                        <th class="px-4 py-3 text-start">{{ t('messages.nexus.admin.negotiations.terms') }}</th>
                        <th class="px-4 py-3 text-start">{{ t('messages.nexus.negotiation.index.round') }}</th>
                        <th class="px-4 py-3 text-start">{{ t('messages.nexus.negotiation.index.status') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach ($rows as $row)
                        @php $negotiation = $row['negotiation']; @endphp
                        <tr>
                            <td class="px-4 py-3 text-gray-400">#{{ $negotiation->id() }}</td>
                            <td class="px-4 py-3">{{ dashboard_language()->value === 'fa' ? $row['initiatorNameFa'] : $row['initiatorNameEn'] }}</td>
                            <td class="px-4 py-3">{{ dashboard_language()->value === 'fa' ? $row['counterpartyNameFa'] : $row['counterpartyNameEn'] }}</td>
                            <td class="px-4 py-3 text-gray-500">
                                {{ $negotiation->catalogItemType()->value === 'product' ? t('messages.nexus.admin.negotiations.item_product') : t('messages.nexus.admin.negotiations.item_service') }}
                                #{{ $negotiation->catalogItemId() }}
                            </td>
                            <td class="px-4 py-3 text-gray-500">
                                {{ number_format($negotiation->currentTerms()->toArray()['priceAmount'] / 100) }} {{ $negotiation->currentTerms()->toArray()['priceCurrency'] }}
                                × {{ $negotiation->currentTerms()->toArray()['quantity'] }}
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $negotiation->roundCount() }}/{{ $negotiation->maxRounds() }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-medium {{ $statusClasses[$negotiation->status()->value] ?? 'bg-gray-100 text-gray-500' }}">
                                    {{ t('messages.nexus.negotiation.status.'.$negotiation->status()->value) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-end">
                                <a href="{{ route('dashboard.nexus.negotiations.show', $negotiation->id()) }}" class="text-sm font-medium text-blue-600 hover:text-blue-800">
                                    {{ t('messages.nexus.admin.negotiations.view') }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection

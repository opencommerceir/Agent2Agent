@extends('layouts.dashboard')

@section('title', t('messages.nexus.admin.escrows.title'))

@section('content')
    <h1 class="mb-6 text-xl font-semibold">{{ t('messages.nexus.admin.escrows.title') }}</h1>

    @if (session('status'))
        <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-700">{{ session('status') }}</div>
    @endif

    @if (empty($disputed))
        <p class="text-sm text-gray-500">{{ t('messages.nexus.admin.escrows.empty') }}</p>
    @else
        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">{{ t('messages.nexus.admin.escrows.contract') }}</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">{{ t('messages.nexus.admin.escrows.gross') }}</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">{{ t('messages.nexus.admin.escrows.fee') }}</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">{{ t('messages.nexus.admin.escrows.net') }}</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">{{ t('messages.nexus.admin.escrows.reason') }}</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach ($disputed as $escrow)
                        <tr>
                            <td class="px-4 py-2">#{{ $escrow->contractId() }}</td>
                            <td class="px-4 py-2">{{ number_format($escrow->grossAmount() / 100) }} {{ $escrow->currency() }}</td>
                            <td class="px-4 py-2">{{ number_format($escrow->platformFeeAmount() / 100) }} {{ $escrow->currency() }}</td>
                            <td class="px-4 py-2">{{ number_format($escrow->netAmount() / 100) }} {{ $escrow->currency() }}</td>
                            <td class="px-4 py-2">{{ $escrow->disputeReason() ?? '—' }}</td>
                            <td class="px-4 py-2 text-right">
                                <form method="POST" action="{{ route('dashboard.nexus.escrows.refund', $escrow->id()) }}">
                                    @csrf
                                    <button type="submit" class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700">
                                        {{ t('messages.nexus.admin.escrows.refund') }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection

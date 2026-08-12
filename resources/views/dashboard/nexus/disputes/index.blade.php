@extends('layouts.dashboard')

@section('title', t('messages.nexus.admin.disputes.title'))

@section('content')
    <h1 class="mb-6 text-xl font-semibold">{{ t('messages.nexus.admin.disputes.title') }}</h1>

    @if (session('status'))
        <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-700">{{ session('status') }}</div>
    @endif

    @foreach (['open' => $open, 'mediation' => $mediation] as $group => $cases)
        <h2 class="mb-2 mt-6 text-sm font-semibold text-gray-700">{{ t('messages.nexus.admin.disputes.status.'.$group) }}</h2>

        @if (empty($cases))
            <p class="text-sm text-gray-500">{{ t('messages.nexus.admin.disputes.empty') }}</p>
        @else
            <div class="space-y-4">
                @foreach ($cases as $case)
                    <div class="rounded-lg border border-gray-200 bg-white p-4">
                        <div class="mb-2 flex items-center justify-between">
                            <p class="text-sm font-medium">#{{ $case->id() }} — {{ t('messages.nexus.admin.disputes.negotiation') }} #{{ $case->negotiationId() }}</p>
                            <span class="text-xs text-gray-400">{{ t('messages.nexus.admin.disputes.opened_by') }} #{{ $case->openedByBusinessId() }}</span>
                        </div>
                        <p class="mb-2 text-sm text-gray-600">{{ $case->reason() ?? '—' }}</p>

                        @if ($case->evidence() !== [])
                            <div class="mb-3 space-y-1 rounded-md bg-gray-50 p-2 text-xs text-gray-600">
                                @foreach ($case->evidence() as $entry)
                                    <p><span class="font-medium">#{{ $entry['businessId'] }}:</span> {{ $entry['note'] }}</p>
                                @endforeach
                            </div>
                        @endif

                        <div class="flex gap-2">
                            @if ($group === 'open')
                                <form method="POST" action="{{ route('dashboard.nexus.disputes.mediate', $case->id()) }}">
                                    @csrf
                                    <button type="submit" class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                        {{ t('messages.nexus.admin.disputes.move_to_mediation') }}
                                    </button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('dashboard.nexus.disputes.arbitrate', $case->id()) }}">
                                @csrf
                                <input type="hidden" name="resolution" value="refund_buyer">
                                <button type="submit" class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700">
                                    {{ t('messages.nexus.admin.disputes.refund_buyer') }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('dashboard.nexus.disputes.arbitrate', $case->id()) }}">
                                @csrf
                                <input type="hidden" name="resolution" value="release_seller">
                                <button type="submit" class="rounded-md bg-green-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-green-700">
                                    {{ t('messages.nexus.admin.disputes.release_seller') }}
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endforeach
@endsection

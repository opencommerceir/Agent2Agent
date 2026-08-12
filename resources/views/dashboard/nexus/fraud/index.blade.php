@extends('layouts.dashboard')

@section('title', t('messages.nexus.admin.fraud.title'))

@section('content')
    <h1 class="mb-6 text-xl font-semibold">{{ t('messages.nexus.admin.fraud.title') }}</h1>

    @if (session('status'))
        <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-700">{{ session('status') }}</div>
    @endif

    <div class="mb-6 flex items-center gap-3">
        <form method="POST" action="{{ route('dashboard.nexus.fraud.run-detection') }}">
            @csrf
            <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                {{ t('messages.nexus.admin.fraud.run_detection') }}
            </button>
        </form>

        <form method="POST" action="{{ route('dashboard.nexus.fraud.suspend') }}" class="flex items-center gap-2">
            @csrf
            <input type="number" name="business_id" placeholder="{{ t('messages.nexus.admin.fraud.business_id') }}" class="w-32 rounded-md border border-gray-300 px-3 py-2 text-sm">
            <input type="text" name="reason" placeholder="{{ t('messages.nexus.admin.fraud.reason') }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm">
            <button type="submit" class="rounded-md border border-red-300 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50">
                {{ t('messages.nexus.admin.fraud.suspend_manually') }}
            </button>
        </form>
    </div>

    <h2 class="mb-2 text-sm font-semibold text-gray-700">{{ t('messages.nexus.admin.fraud.suspended_businesses') }}</h2>
    @if (empty($suspended))
        <p class="mb-6 text-sm text-gray-500">{{ t('messages.nexus.admin.fraud.empty') }}</p>
    @else
        <div class="mb-6 overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">ID</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">{{ t('messages.nexus.admin.fraud.business') }}</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach ($suspended as $business)
                        <tr>
                            <td class="px-4 py-2">#{{ $business->id() }}</td>
                            <td class="px-4 py-2">{{ $business->nameEn() }}</td>
                            <td class="px-4 py-2 text-right">
                                <form method="POST" action="{{ route('dashboard.nexus.fraud.reactivate', $business->id()) }}">
                                    @csrf
                                    <button type="submit" class="rounded-md bg-green-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-green-700">
                                        {{ t('messages.nexus.admin.fraud.reactivate') }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <h2 class="mb-2 text-sm font-semibold text-gray-700">{{ t('messages.nexus.admin.fraud.pending_appeals') }}</h2>
    @if (empty($pendingAppeals))
        <p class="text-sm text-gray-500">{{ t('messages.nexus.admin.fraud.empty') }}</p>
    @else
        <div class="space-y-3">
            @foreach ($pendingAppeals as $appeal)
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <p class="mb-1 text-sm font-medium">#{{ $appeal->id() }} — {{ t('messages.nexus.admin.fraud.business') }} #{{ $appeal->businessId() }}</p>
                    <p class="mb-3 text-sm text-gray-600">{{ $appeal->message() }}</p>
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('dashboard.nexus.fraud.appeals.resolve', $appeal->id()) }}">
                            @csrf
                            <input type="hidden" name="approve" value="1">
                            <button type="submit" class="rounded-md bg-green-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-green-700">
                                {{ t('messages.nexus.admin.fraud.approve') }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('dashboard.nexus.fraud.appeals.resolve', $appeal->id()) }}">
                            @csrf
                            <input type="hidden" name="approve" value="0">
                            <button type="submit" class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                {{ t('messages.nexus.admin.fraud.deny') }}
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection

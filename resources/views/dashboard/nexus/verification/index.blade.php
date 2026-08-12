@extends('layouts.dashboard')

@section('title', t('messages.nexus.admin.verification.title'))

@section('content')
    <h1 class="mb-6 text-xl font-semibold">{{ t('messages.nexus.admin.verification.title') }}</h1>

    @if (session('status'))
        <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-700">{{ session('status') }}</div>
    @endif

    <h2 class="mb-2 text-sm font-semibold text-gray-700">{{ t('messages.nexus.admin.verification.pending_businesses') }}</h2>
    @if (empty($pendingBusinesses))
        <p class="mb-6 text-sm text-gray-500">{{ t('messages.nexus.admin.verification.empty') }}</p>
    @else
        <div class="mb-6 overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <tbody class="divide-y divide-gray-200">
                    @foreach ($pendingBusinesses as $business)
                        <tr>
                            <td class="px-4 py-2">#{{ $business->id() }}</td>
                            <td class="px-4 py-2">{{ $business->nameEn() }}</td>
                            <td class="px-4 py-2 text-right">
                                <form method="POST" action="{{ route('dashboard.nexus.verification.businesses.verify', $business->id()) }}">
                                    @csrf
                                    <button type="submit" class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700">
                                        {{ t('messages.nexus.admin.verification.verify') }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <h2 class="mb-2 text-sm font-semibold text-gray-700">{{ t('messages.nexus.admin.verification.pending_products') }}</h2>
    @if (empty($pendingProducts))
        <p class="mb-6 text-sm text-gray-500">{{ t('messages.nexus.admin.verification.empty') }}</p>
    @else
        <div class="mb-6 overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <tbody class="divide-y divide-gray-200">
                    @foreach ($pendingProducts as $product)
                        <tr>
                            <td class="px-4 py-2">#{{ $product->id() }}</td>
                            <td class="px-4 py-2">{{ $product->nameEn() }}</td>
                            <td class="px-4 py-2 text-right">
                                <form method="POST" action="{{ route('dashboard.nexus.verification.products.verify', $product->id()) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700">{{ t('messages.nexus.admin.verification.verify') }}</button>
                                </form>
                                <form method="POST" action="{{ route('dashboard.nexus.verification.products.reject', $product->id()) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">{{ t('messages.nexus.admin.verification.reject') }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <h2 class="mb-2 text-sm font-semibold text-gray-700">{{ t('messages.nexus.admin.verification.pending_services') }}</h2>
    @if (empty($pendingServices))
        <p class="text-sm text-gray-500">{{ t('messages.nexus.admin.verification.empty') }}</p>
    @else
        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <tbody class="divide-y divide-gray-200">
                    @foreach ($pendingServices as $service)
                        <tr>
                            <td class="px-4 py-2">#{{ $service->id() }}</td>
                            <td class="px-4 py-2">{{ $service->nameEn() }}</td>
                            <td class="px-4 py-2 text-right">
                                <form method="POST" action="{{ route('dashboard.nexus.verification.services.verify', $service->id()) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700">{{ t('messages.nexus.admin.verification.verify') }}</button>
                                </form>
                                <form method="POST" action="{{ route('dashboard.nexus.verification.services.reject', $service->id()) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">{{ t('messages.nexus.admin.verification.reject') }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection

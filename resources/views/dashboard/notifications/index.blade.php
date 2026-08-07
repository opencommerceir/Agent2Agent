@extends('layouts.dashboard')

@section('title', t('messages.notifications.list'))

@section('content')
    <h1 class="mb-6 text-xl font-semibold">{{ t('messages.notifications.list') }}</h1>

    @include('dashboard.partials.help', [
        'title' => t('messages.help.notifications_index.title'),
        'description' => t('messages.help.notifications_index.description'),
    ])

    <form method="GET" action="{{ route('dashboard.notifications.index') }}" class="mb-4 flex flex-wrap gap-4">
        <div>
            <label class="mb-1 block text-sm font-medium">{{ t('messages.settings.select_tenant') }}</label>
            <select name="tenant_id" onchange="this.form.submit()" class="rounded-md border border-gray-300 px-3 py-2 text-sm">
                @foreach ($tenants as $tenant)
                    <option value="{{ $tenant->id() }}" @selected($selectedTenantId === $tenant->id())>{{ $tenant->name() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">{{ t('messages.notifications.filter_by_type') }}</label>
            <select name="type" onchange="this.form.submit()" class="rounded-md border border-gray-300 px-3 py-2 text-sm">
                <option value="">{{ t('messages.notifications.all_types') }}</option>
                @foreach (['order_placed', 'shipment_status_changed', 'points_earned', 'ticket_created'] as $type)
                    <option value="{{ $type }}" @selected($selectedType === $type)>{{ $type }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">{{ t('messages.notifications.filter_by_status') }}</label>
            <select name="status" onchange="this.form.submit()" class="rounded-md border border-gray-300 px-3 py-2 text-sm">
                <option value="">{{ t('messages.orders.all_statuses') }}</option>
                @foreach (['pending', 'sent', 'delivered', 'failed'] as $status)
                    <option value="{{ $status }}" @selected($selectedStatus === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </div>
    </form>

    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
        <table class="w-full text-start text-sm">
            <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3 text-start">{{ t('messages.notifications.type') }}</th>
                    <th class="px-4 py-3 text-start">{{ t('messages.notifications.channel') }}</th>
                    <th class="px-4 py-3 text-start">{{ t('messages.notifications.recipient') }}</th>
                    <th class="px-4 py-3 text-start">{{ t('messages.notifications.subject') }}</th>
                    <th class="px-4 py-3 text-start">{{ t('messages.notifications.status') }}</th>
                    <th class="px-4 py-3 text-start">{{ t('messages.notifications.sent_at') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($notifications as $notification)
                    <tr class="border-b border-gray-100 last:border-0">
                        <td class="px-4 py-3">{{ $notification['type'] }}</td>
                        <td class="px-4 py-3">{{ $notification['channelType'] }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $notification['recipient'] }}</td>
                        <td class="px-4 py-3">{{ $notification['subject'] }}</td>
                        <td class="px-4 py-3">{{ $notification['status'] }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $notification['sentAt'] ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">{{ t('messages.notifications.no_notifications') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

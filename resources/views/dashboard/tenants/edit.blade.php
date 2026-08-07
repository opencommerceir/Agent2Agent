@extends('layouts.dashboard')

@section('title', t('messages.tenants.edit'))

@section('content')
    <h1 class="mb-6 text-xl font-semibold">{{ t('messages.tenants.edit') }}</h1>

    @include('dashboard.partials.help', [
        'title' => t('messages.help.tenants_edit.title'),
        'description' => t('messages.help.tenants_edit.description'),
    ])

    <form method="POST" action="{{ route('dashboard.tenants.update', $tenant->id()) }}" class="max-w-lg space-y-4 rounded-lg border border-gray-200 bg-white p-6">
        @csrf
        @method('PUT')

        <div>
            <label class="mb-1 block text-sm font-medium">{{ t('messages.tenants.name') }}</label>
            <input type="text" name="name" value="{{ old('name', $tenant->name()) }}" required class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">{{ t('messages.tenants.domain') }}</label>
            <input type="text" value="{{ $tenant->slug() }}" disabled class="w-full rounded-md border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-gray-500">
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">{{ t('messages.tenants.status') }}</label>
            <select name="status" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                @foreach (['pending', 'active', 'suspended'] as $status)
                    <option value="{{ $status }}" @selected(old('status', $tenant->status()->value) === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">{{ t('messages.common.save') }}</button>
            <a href="{{ route('dashboard.tenants.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">{{ t('messages.common.back') }}</a>
        </div>
    </form>
@endsection

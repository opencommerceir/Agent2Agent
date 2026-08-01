@extends('layouts.dashboard')

@section('title', t('messages.settings.title'))

@section('content')
    <h1 class="mb-6 text-xl font-semibold">{{ t('messages.settings.title') }}</h1>

    <form method="GET" action="{{ route('dashboard.settings.index') }}" class="mb-4 max-w-xs">
        <label class="mb-1 block text-sm font-medium">{{ t('messages.settings.select_tenant') }}</label>
        <select name="tenant_id" onchange="this.form.submit()" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
            @foreach ($tenants as $t)
                <option value="{{ $t->id() }}" @selected($tenant && $tenant->id() === $t->id())>{{ $t->name() }}</option>
            @endforeach
        </select>
    </form>

    @if ($tenant)
        <form method="POST" action="{{ route('dashboard.settings.update') }}" class="max-w-lg space-y-4 rounded-lg border border-gray-200 bg-white p-6">
            @csrf
            @method('PUT')
            <input type="hidden" name="tenant_id" value="{{ $tenant->id() }}">

            <div>
                <label class="mb-1 block text-sm font-medium">{{ t('messages.settings.default_language') }}</label>
                <select name="default_language" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                    <option value="en" @selected($tenant->defaultLanguage()->value === 'en')>English</option>
                    <option value="fa" @selected($tenant->defaultLanguage()->value === 'fa')>فارسی</option>
                </select>
            </div>

            <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">{{ t('messages.common.save') }}</button>
        </form>
    @endif
@endsection

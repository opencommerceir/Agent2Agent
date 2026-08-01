@extends('layouts.dashboard')

@section('title', t('messages.tenants.create'))

@section('content')
    <h1 class="mb-6 text-xl font-semibold">{{ t('messages.tenants.create') }}</h1>

    <form method="POST" action="{{ route('dashboard.tenants.store') }}" class="max-w-lg space-y-4 rounded-lg border border-gray-200 bg-white p-6">
        @csrf

        <div>
            <label class="mb-1 block text-sm font-medium">{{ t('messages.tenants.name') }}</label>
            <input type="text" name="name" value="{{ old('name') }}" required class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">{{ t('messages.tenants.domain') }}</label>
            <input type="text" name="slug" value="{{ old('slug') }}" required class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
        </div>

        <div class="flex gap-3">
            <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">{{ t('messages.common.save') }}</button>
            <a href="{{ route('dashboard.tenants.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">{{ t('messages.common.back') }}</a>
        </div>
    </form>
@endsection

@extends('layouts.dashboard')

@section('title', t('messages.agents.edit'))

@section('content')
    <h1 class="mb-6 text-xl font-semibold">{{ t('messages.agents.edit') }}</h1>

    <form method="POST" action="{{ route('dashboard.agents.update', $agent->id()) }}" class="max-w-lg space-y-4 rounded-lg border border-gray-200 bg-white p-6">
        @csrf
        @method('PUT')

        <div>
            <label class="mb-1 block text-sm font-medium">{{ t('messages.tenants.name') }}</label>
            <input type="text" name="name" value="{{ old('name', $agent->name()) }}" required class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">{{ t('messages.agents.type') }}</label>
            <select name="type" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                @foreach (['shopping', 'analytics', 'customer_service', 'custom'] as $type)
                    <option value="{{ $type }}" @selected(old('type', $agent->type()->value) === $type)>{{ $type }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">{{ t('messages.common.save') }}</button>
            <a href="{{ route('dashboard.agents.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">{{ t('messages.common.back') }}</a>
        </div>
    </form>
@endsection

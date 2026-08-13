@extends('nexus::layouts.app')

@section('title', t('messages.nexus.catalog.services.add'))

@section('content')
    <div class="mx-auto max-w-2xl">
        <x-nexus-panel :title="t('messages.nexus.catalog.services.add')">
            @if ($errors->any())
                <div class="mb-4 rounded-md border border-nexus-error/40 bg-nexus-error/10 px-4 py-3 text-sm text-nexus-error">
                    <ul class="list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('nexus.catalog.services.store') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="mb-1 block text-sm text-nexus-text">{{ t('messages.nexus.catalog.fields.name_fa') }}</label>
                    <input type="text" name="name_fa" dir="rtl" value="{{ old('name_fa') }}" required class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text focus:border-nexus-cyan focus:outline-none">
                </div>

                <div>
                    <label class="mb-1 block text-sm text-nexus-text">{{ t('messages.nexus.catalog.fields.name_en') }}</label>
                    <input type="text" name="name_en" dir="ltr" value="{{ old('name_en') }}" required class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text focus:border-nexus-cyan focus:outline-none">
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm text-nexus-text">{{ t('messages.nexus.catalog.fields.hourly_price') }}</label>
                        <input type="number" name="price_amount" min="0" value="{{ old('price_amount') }}" required class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text focus:border-nexus-cyan focus:outline-none">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-nexus-text">{{ t('messages.nexus.catalog.fields.duration_minutes') }}</label>
                        <input type="number" name="duration_minutes" min="1" value="{{ old('duration_minutes') }}" class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text focus:border-nexus-cyan focus:outline-none">
                    </div>
                </div>

                <button type="submit" class="w-full rounded-md bg-nexus-cyan/20 px-4 py-2 text-sm font-semibold text-nexus-cyan hover:bg-nexus-cyan/30">
                    {{ t('messages.nexus.catalog.submit') }}
                </button>
            </form>

            <p class="mt-4 text-center text-sm">
                <a href="{{ route('nexus.catalog.index') }}" class="text-nexus-cyan hover:underline">{{ t('messages.nexus.catalog.back') }}</a>
            </p>
        </x-nexus-panel>
    </div>
@endsection

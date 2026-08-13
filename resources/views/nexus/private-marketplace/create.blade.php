@extends('nexus::layouts.app')

@section('title', t('messages.nexus.private_marketplace.create_new'))

@section('content')
    <div class="mx-auto max-w-2xl">
        <x-nexus-panel :title="t('messages.nexus.private_marketplace.create_new')">
            @if ($errors->any())
                <div class="mb-4 rounded-md border border-nexus-error/40 bg-nexus-error/10 px-4 py-3 text-sm text-nexus-error">
                    <ul class="list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('nexus.private-marketplace.store') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="mb-1 block text-sm text-nexus-text">{{ t('messages.nexus.private_marketplace.form.name_fa') }}</label>
                    <input type="text" name="name_fa" required class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text focus:border-nexus-cyan focus:outline-none">
                </div>

                <div>
                    <label class="mb-1 block text-sm text-nexus-text">{{ t('messages.nexus.private_marketplace.form.name_en') }}</label>
                    <input type="text" name="name_en" required class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text focus:border-nexus-cyan focus:outline-none">
                </div>

                <div>
                    <label class="mb-1 block text-sm text-nexus-text">{{ t('messages.nexus.private_marketplace.form.branding_color') }}</label>
                    <input type="color" name="branding_primary_color" value="#00F0FF" class="h-10 w-20 rounded-md border border-nexus-border bg-nexus-surface-1">
                </div>

                <button type="submit" class="w-full rounded-md bg-nexus-cyan/20 px-4 py-2 text-sm font-semibold text-nexus-cyan hover:bg-nexus-cyan/30">
                    {{ t('messages.nexus.private_marketplace.form.submit') }}
                </button>
            </form>
        </x-nexus-panel>
    </div>
@endsection

@extends('nexus::layouts.app')

@section('title', t('messages.nexus.business.password.force_change.title'))

@section('content')
    <div class="mx-auto max-w-md">
        <x-nexus-panel :title="t('messages.nexus.business.password.force_change.title')">
            <p class="mb-4 text-sm text-nexus-text-muted">{{ t('messages.nexus.business.password.force_change.description') }}</p>

            @if ($errors->any())
                <div class="mb-4 rounded-md border border-nexus-error/40 bg-nexus-error/10 px-4 py-3 text-sm text-nexus-error">
                    <ul class="list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('nexus.business.password.force-change.store') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="mb-1 block text-sm text-nexus-text">{{ t('messages.nexus.business.password.force_change.new_password') }}</label>
                    <input type="password" name="password" required class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text focus:border-nexus-cyan focus:outline-none">
                </div>

                <div>
                    <label class="mb-1 block text-sm text-nexus-text">{{ t('messages.nexus.business.password.force_change.confirm_password') }}</label>
                    <input type="password" name="password_confirmation" required class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text focus:border-nexus-cyan focus:outline-none">
                </div>

                <button type="submit" class="w-full rounded-md bg-nexus-cyan/20 px-4 py-2 text-sm font-semibold text-nexus-cyan hover:bg-nexus-cyan/30">
                    {{ t('messages.nexus.business.password.force_change.submit') }}
                </button>
            </form>
        </x-nexus-panel>
    </div>
@endsection

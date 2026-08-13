@extends('nexus::layouts.app')

@section('title', t('messages.nexus.business.mfa.challenge.title'))

@section('content')
    <div class="mx-auto max-w-md">
        <x-nexus-panel :title="t('messages.nexus.business.mfa.challenge.title')">
            <p class="mb-4 text-sm text-nexus-text-muted">{{ t('messages.nexus.business.mfa.challenge.description') }}</p>

            @if ($errors->any())
                <div class="mb-4 rounded-md border border-nexus-error/40 bg-nexus-error/10 px-4 py-3 text-sm text-nexus-error">
                    <ul class="list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('nexus.business.mfa-challenge.verify') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="mb-1 block text-sm text-nexus-text">{{ t('messages.nexus.business.mfa.challenge.code') }}</label>
                    <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required autofocus class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text focus:border-nexus-cyan focus:outline-none">
                </div>

                <button type="submit" class="w-full rounded-md bg-nexus-cyan/20 px-4 py-2 text-sm font-semibold text-nexus-cyan hover:bg-nexus-cyan/30">
                    {{ t('messages.nexus.business.mfa.challenge.submit') }}
                </button>
            </form>

            <p class="mt-4 text-center text-xs text-nexus-text-muted">{{ t('messages.nexus.business.mfa.challenge.recovery_hint') }}</p>
        </x-nexus-panel>
    </div>
@endsection

@extends('nexus::layouts.app')

@section('title', t('messages.nexus.business.mfa.settings.title'))

@section('content')
    <div class="mx-auto max-w-md space-y-4">
        @if (session('status'))
            <div class="rounded-md border border-nexus-success/40 bg-nexus-success/10 px-4 py-2 text-sm text-nexus-success">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="rounded-md border border-nexus-error/40 bg-nexus-error/10 px-4 py-3 text-sm text-nexus-error">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-nexus-panel :title="t('messages.nexus.business.mfa.settings.title')">
            @if ($recoveryCodes)
                <p class="mb-2 text-sm text-nexus-success">{{ t('messages.nexus.business.mfa.settings.enabled_banner') }}</p>
                <p class="mb-3 text-xs text-nexus-text-muted">{{ t('messages.nexus.business.mfa.settings.recovery_codes_hint') }}</p>
                <div class="grid grid-cols-2 gap-2 rounded-md bg-nexus-bg/40 p-3 font-mono text-sm text-nexus-text">
                    @foreach ($recoveryCodes as $recoveryCode)
                        <span>{{ $recoveryCode }}</span>
                    @endforeach
                </div>
            @elseif ($owner->mfa_enabled_at)
                <x-status-badge status="success" :label="t('messages.nexus.business.mfa.settings.enabled')" />
                <form method="POST" action="{{ route('nexus.business.mfa.disable') }}" class="mt-4 space-y-3">
                    @csrf
                    <div>
                        <label class="mb-1 block text-sm text-nexus-text">{{ t('messages.nexus.business.mfa.disable.password') }}</label>
                        <input type="password" name="password" required class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text focus:border-nexus-cyan focus:outline-none">
                    </div>
                    <button type="submit" class="w-full rounded-md border border-nexus-error/40 px-4 py-2 text-sm text-nexus-error hover:bg-nexus-error/10">
                        {{ t('messages.nexus.business.mfa.disable.submit') }}
                    </button>
                </form>
            @elseif ($setup)
                <p class="mb-3 text-sm text-nexus-text-muted">{{ t('messages.nexus.business.mfa.setup.description') }}</p>
                <div class="mb-3 space-y-1 rounded-md bg-nexus-bg/40 p-3 font-mono text-xs text-nexus-text">
                    <p>{{ t('messages.nexus.business.mfa.setup.secret') }}: {{ $setup['secret'] }}</p>
                    <p class="break-all">{{ $setup['otpauthUri'] }}</p>
                </div>
                <form method="POST" action="{{ route('nexus.business.mfa.confirm') }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="mb-1 block text-sm text-nexus-text">{{ t('messages.nexus.business.mfa.setup.code') }}</label>
                        <input type="text" name="code" inputmode="numeric" required class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text focus:border-nexus-cyan focus:outline-none">
                    </div>
                    <button type="submit" class="w-full rounded-md bg-nexus-cyan/20 px-4 py-2 text-sm font-semibold text-nexus-cyan hover:bg-nexus-cyan/30">
                        {{ t('messages.nexus.business.mfa.setup.submit') }}
                    </button>
                </form>
            @else
                <p class="mb-3 text-sm text-nexus-text-muted">{{ t('messages.nexus.business.mfa.settings.disabled_description') }}</p>
                <form method="POST" action="{{ route('nexus.business.mfa.start') }}">
                    @csrf
                    <button type="submit" class="w-full rounded-md bg-nexus-cyan/20 px-4 py-2 text-sm font-semibold text-nexus-cyan hover:bg-nexus-cyan/30">
                        {{ t('messages.nexus.business.mfa.settings.enable') }}
                    </button>
                </form>
            @endif
        </x-nexus-panel>
    </div>
@endsection

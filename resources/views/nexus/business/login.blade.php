@extends('nexus::layouts.app')

@section('title', t('messages.nexus.business.login.title'))

@section('content')
    <div class="mx-auto max-w-md">
        <x-nexus-panel :title="t('messages.nexus.business.login.title')">
            @if ($errors->any())
                <div class="mb-4 rounded-md border border-nexus-error/40 bg-nexus-error/10 px-4 py-3 text-sm text-nexus-error">
                    <ul class="list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('nexus.business.login.store') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="mb-1 block text-sm text-nexus-text">{{ t('messages.nexus.business.login.email') }}</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text focus:border-nexus-cyan focus:outline-none">
                </div>

                <div>
                    <label class="mb-1 block text-sm text-nexus-text">{{ t('messages.nexus.business.login.password') }}</label>
                    <input type="password" name="password" required class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text focus:border-nexus-cyan focus:outline-none">
                </div>

                <label class="flex items-center gap-2 text-sm text-nexus-text-muted">
                    <input type="checkbox" name="remember" value="1" class="rounded border-nexus-border bg-nexus-surface-1">
                    {{ t('messages.nexus.business.login.remember') }}
                </label>

                <button type="submit" class="w-full rounded-md bg-nexus-cyan/20 px-4 py-2 text-sm font-semibold text-nexus-cyan hover:bg-nexus-cyan/30">
                    {{ t('messages.nexus.business.login.submit') }}
                </button>
            </form>

            <div class="my-4 flex items-center gap-3 text-xs text-nexus-text-muted">
                <div class="h-px flex-1 bg-nexus-border"></div>
                {{ t('messages.nexus.business.login.or') }}
                <div class="h-px flex-1 bg-nexus-border"></div>
            </div>

            <a href="{{ route('nexus.business.oauth.redirect', 'google') }}" class="block w-full rounded-md border border-nexus-border px-4 py-2 text-center text-sm text-nexus-text hover:bg-nexus-surface-1">
                {{ t('messages.nexus.business.login.continue_with_google') }}
            </a>

            <p class="mt-4 text-center text-sm text-nexus-text-muted">
                {{ t('messages.nexus.business.login.no_account') }}
                <a href="{{ route('nexus.business.register') }}" class="text-nexus-cyan hover:underline">{{ t('messages.nexus.business.register.submit') }}</a>
            </p>
        </x-nexus-panel>
    </div>
@endsection

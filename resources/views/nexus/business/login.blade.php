@extends('nexus::layouts.app')

@section('title', t('messages.nexus.business.login.title'))

@section('content')
    <div class="mx-auto max-w-md">
        <x-nexus-panel :title="t('messages.nexus.business.login.title')">
            @if ($errors->any())
                <div class="mb-4 rounded-md border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-300">
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
                    <label class="mb-1 block text-sm text-slate-300">{{ t('messages.nexus.business.login.email') }}</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus class="w-full rounded-md border border-nexus-border bg-nexus-surface px-3 py-2 text-sm text-slate-100 focus:border-nexus-cyan focus:outline-none">
                </div>

                <div>
                    <label class="mb-1 block text-sm text-slate-300">{{ t('messages.nexus.business.login.password') }}</label>
                    <input type="password" name="password" required class="w-full rounded-md border border-nexus-border bg-nexus-surface px-3 py-2 text-sm text-slate-100 focus:border-nexus-cyan focus:outline-none">
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-400">
                    <input type="checkbox" name="remember" value="1" class="rounded border-nexus-border bg-nexus-surface">
                    {{ t('messages.nexus.business.login.remember') }}
                </label>

                <button type="submit" class="w-full rounded-md bg-nexus-cyan/20 px-4 py-2 text-sm font-semibold text-nexus-cyan hover:bg-nexus-cyan/30">
                    {{ t('messages.nexus.business.login.submit') }}
                </button>
            </form>

            <p class="mt-4 text-center text-sm text-slate-400">
                {{ t('messages.nexus.business.login.no_account') }}
                <a href="{{ route('nexus.business.register') }}" class="text-nexus-cyan hover:underline">{{ t('messages.nexus.business.register.submit') }}</a>
            </p>
        </x-nexus-panel>
    </div>
@endsection

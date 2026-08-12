@extends('nexus::layouts.app')

@section('title', t('messages.nexus.credit.purchase.title'))

@section('content')
    <div class="mx-auto max-w-3xl space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-lg text-nexus-text">{{ t('messages.nexus.credit.purchase.title') }}</h1>
            <a href="{{ route('nexus.business.dashboard') }}" class="text-sm text-nexus-text-muted hover:text-nexus-text">
                {{ t('messages.nexus.credit.purchase.back_to_dashboard') }}
            </a>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            @foreach ($packages as $package)
                <x-nexus-panel :glow="$package->value === 'professional' ? 'cyan' : 'none'">
                    <div class="space-y-3 text-center">
                        <p class="text-sm text-nexus-text">{{ t('messages.nexus.credit.purchase.package.'.$package->value) }}</p>
                        <p class="text-2xl text-nexus-text">{{ number_format($package->creditsGranted()) }}</p>
                        <p class="text-xs text-nexus-text-muted">{{ t('messages.nexus.credit.purchase.credits') }}</p>
                        <p class="text-sm text-nexus-text-muted">{{ number_format($package->priceAmountToman()) }} {{ t('messages.nexus.credit.purchase.toman') }}</p>
                        <form method="POST" action="{{ route('nexus.credit.purchase.store') }}">
                            @csrf
                            <input type="hidden" name="package" value="{{ $package->value }}">
                            <button type="submit" class="w-full rounded-md border border-nexus-cyan/40 px-3 py-2 text-sm text-nexus-text hover:bg-nexus-surface-1">
                                {{ t('messages.nexus.credit.purchase.buy') }}
                            </button>
                        </form>
                    </div>
                </x-nexus-panel>
            @endforeach
        </div>
    </div>
@endsection

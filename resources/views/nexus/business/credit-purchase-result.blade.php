@extends('nexus::layouts.app')

@section('title', t('messages.nexus.credit.purchase.'.($successful ? 'confirmed_title' : 'failed_title')))

@section('content')
    <div class="mx-auto max-w-lg">
        <x-nexus-panel :glow="$successful ? 'cyan' : 'none'">
            <div class="space-y-3 text-center">
                <x-status-badge :status="$successful ? 'success' : 'error'" :label="t('messages.nexus.credit.purchase.'.($successful ? 'confirmed_title' : 'failed_title'))" />

                @if ($successful)
                    <p class="text-sm text-nexus-text">
                        {{ number_format($creditsGranted) }} {{ t('messages.nexus.credit.purchase.confirmed_body') }}
                    </p>
                @else
                    <p class="text-sm text-nexus-text">{{ t('messages.nexus.credit.purchase.failed_body') }}</p>
                    <p class="text-xs text-nexus-text-muted">{{ $message }}</p>
                @endif

                <a href="{{ route('nexus.business.dashboard') }}" class="inline-block rounded-md border border-nexus-border px-3 py-2 text-sm text-nexus-text hover:bg-nexus-surface-1">
                    {{ t('messages.nexus.credit.purchase.back_to_dashboard') }}
                </a>
            </div>
        </x-nexus-panel>
    </div>
@endsection

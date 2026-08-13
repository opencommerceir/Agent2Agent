@extends('nexus::layouts.app')

@section('title', t('messages.nexus.holding.title'))

@section('content')
    <div class="mx-auto max-w-3xl space-y-4">
        <x-nexus-panel :title="t('messages.nexus.holding.title')">
            @if ($holding)
                <div class="flex items-center justify-between">
                    <span class="text-sm text-nexus-text">{{ dashboard_language()->value === 'fa' ? $holding->nameFa : $holding->nameEn }}</span>
                    <a href="{{ route('nexus.holding.show', $holding->id) }}" class="rounded-md bg-nexus-cyan/20 px-3 py-1.5 text-sm font-semibold text-nexus-cyan hover:bg-nexus-cyan/30">
                        {{ t('messages.nexus.holding.view') }}
                    </a>
                </div>
            @else
                <div class="flex items-center justify-between">
                    <p class="text-sm text-nexus-text-muted">{{ t('messages.nexus.holding.no_holding') }}</p>
                    <a href="{{ route('nexus.holding.create') }}" class="rounded-md bg-nexus-cyan/20 px-3 py-1.5 text-sm font-semibold text-nexus-cyan hover:bg-nexus-cyan/30">
                        {{ t('messages.nexus.holding.create_new') }}
                    </a>
                </div>
            @endif
        </x-nexus-panel>

        <x-nexus-panel :title="t('messages.nexus.holding.invitations.title')">
            @if (count($invitations) === 0)
                <p class="text-sm text-nexus-text-muted">{{ t('messages.nexus.holding.invitations.empty') }}</p>
            @else
                <div class="space-y-3">
                    @foreach ($invitations as $invitation)
                        <div class="flex items-center justify-between rounded-md border border-nexus-border bg-nexus-surface-1 p-4">
                            <div>
                                <p class="text-sm text-nexus-text">{{ $invitation->holdingNameEn }}</p>
                                <p class="text-xs text-nexus-text-muted">{{ t('messages.nexus.holding.invitations.invited_by') }}: {{ $invitation->parentBusinessNameEn }}</p>
                            </div>
                            <div class="flex gap-2">
                                <form method="POST" action="{{ route('nexus.holding.subsidiaries.accept', $invitation->subsidiaryId) }}">
                                    @csrf
                                    <button type="submit" class="rounded-md bg-nexus-success/20 px-3 py-1.5 text-sm font-semibold text-nexus-success hover:bg-nexus-success/30">
                                        {{ t('messages.nexus.holding.invitations.accept') }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('nexus.holding.subsidiaries.reject', $invitation->subsidiaryId) }}">
                                    @csrf
                                    <button type="submit" class="rounded-md border border-nexus-error/40 px-3 py-1.5 text-sm text-nexus-error hover:bg-nexus-error/10">
                                        {{ t('messages.nexus.holding.invitations.reject') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-nexus-panel>
    </div>
@endsection

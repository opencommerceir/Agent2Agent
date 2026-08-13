@extends('nexus::layouts.app')

@section('title', t('messages.nexus.private_marketplace.title'))

@section('content')
    <div class="mx-auto max-w-3xl space-y-4">
        <x-nexus-panel :title="t('messages.nexus.private_marketplace.title')">
            <div class="mb-4 flex items-center justify-between">
                <p class="text-sm text-nexus-text-muted">{{ t('messages.nexus.private_marketplace.how_it_works') }}</p>
                <a href="{{ route('nexus.private-marketplace.create') }}" class="shrink-0 rounded-md bg-nexus-cyan/20 px-3 py-1.5 text-sm font-semibold text-nexus-cyan hover:bg-nexus-cyan/30">
                    {{ t('messages.nexus.private_marketplace.create_new') }}
                </a>
            </div>

            @if (count($marketplaces) === 0)
                <p class="text-sm text-nexus-text-muted">{{ t('messages.nexus.private_marketplace.empty') }}</p>
            @else
                <div class="space-y-3">
                    @foreach ($marketplaces as $marketplace)
                        <a href="{{ route('nexus.private-marketplace.show', $marketplace->id) }}" class="flex items-center justify-between rounded-md border border-nexus-border bg-nexus-surface-1 p-4 hover:border-nexus-cyan/40">
                            <span class="text-sm text-nexus-text">{{ $marketplace->nameEn }}</span>
                            @if ($marketplace->isOwner)
                                <x-status-badge status="info" :label="t('messages.nexus.private_marketplace.owner_badge')" />
                            @endif
                        </a>
                    @endforeach
                </div>
            @endif
        </x-nexus-panel>

        <x-nexus-panel :title="t('messages.nexus.private_marketplace.invitations.title')">
            @if (count($invitations) === 0)
                <p class="text-sm text-nexus-text-muted">{{ t('messages.nexus.private_marketplace.invitations.empty') }}</p>
            @else
                <div class="space-y-3">
                    @foreach ($invitations as $invitation)
                        <div class="flex items-center justify-between rounded-md border border-nexus-border bg-nexus-surface-1 p-4">
                            <span class="text-sm text-nexus-text">{{ $invitation['marketplaceNameEn'] }}</span>
                            <div class="flex gap-2">
                                <form method="POST" action="{{ route('nexus.private-marketplace.members.accept', $invitation['memberId']) }}">
                                    @csrf
                                    <button type="submit" class="rounded-md bg-nexus-success/20 px-3 py-1.5 text-sm font-semibold text-nexus-success hover:bg-nexus-success/30">
                                        {{ t('messages.nexus.private_marketplace.invitations.accept') }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('nexus.private-marketplace.members.reject', $invitation['memberId']) }}">
                                    @csrf
                                    <button type="submit" class="rounded-md border border-nexus-error/40 px-3 py-1.5 text-sm text-nexus-error hover:bg-nexus-error/10">
                                        {{ t('messages.nexus.private_marketplace.invitations.reject') }}
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

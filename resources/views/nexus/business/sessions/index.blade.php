@extends('nexus::layouts.app')

@section('title', t('messages.nexus.business.sessions.title'))

@section('content')
    <div class="mx-auto max-w-3xl">
        <x-nexus-panel :title="t('messages.nexus.business.sessions.title')">
            <div class="space-y-3">
                @foreach ($sessions as $session)
                    <div class="flex items-center justify-between rounded-md border border-nexus-border bg-nexus-surface-1 p-4">
                        <div>
                            <p class="text-sm text-nexus-text">
                                {{ $session->ipAddress ?? '—' }}
                                @if ($session->isCurrent)
                                    <x-status-badge status="success" :label="t('messages.nexus.business.sessions.current')" />
                                @endif
                            </p>
                            <p class="text-xs text-nexus-text-muted">{{ Str::limit($session->userAgent ?? '—', 80) }}</p>
                            <p class="text-xs text-nexus-text-muted">{{ t('messages.nexus.business.sessions.last_activity') }}: {{ \Carbon\Carbon::createFromTimestamp($session->lastActivity)->diffForHumans() }}</p>
                        </div>
                        @unless ($session->isCurrent)
                            <form method="POST" action="{{ route('nexus.business.sessions.destroy', $session->id) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-md border border-nexus-error/40 px-3 py-1.5 text-sm text-nexus-error hover:bg-nexus-error/10">
                                    {{ t('messages.nexus.business.sessions.revoke') }}
                                </button>
                            </form>
                        @endunless
                    </div>
                @endforeach
            </div>
        </x-nexus-panel>
    </div>
@endsection

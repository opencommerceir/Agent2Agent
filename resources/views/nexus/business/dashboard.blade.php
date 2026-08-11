@extends('nexus::layouts.app')

@section('title', $owner->name)

@section('content')
    <div class="mx-auto max-w-3xl space-y-4">
        <x-nexus-panel>
            <div class="flex items-center justify-between">
                <p class="text-sm text-nexus-text">{{ $owner->name }} — {{ $owner->email }}</p>
                <form method="POST" action="{{ route('nexus.business.logout') }}">
                    @csrf
                    <button type="submit" class="rounded-md border border-nexus-border px-3 py-1.5 text-sm text-nexus-text hover:bg-nexus-surface-1">
                        {{ t('messages.nav.logout') }}
                    </button>
                </form>
            </div>
        </x-nexus-panel>
    </div>
@endsection

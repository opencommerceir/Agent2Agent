@extends('nexus::layouts.app')

@section('title', t('messages.nexus.automation.title'))

@section('content')
    <div class="mx-auto max-w-3xl space-y-4">
        <x-nexus-panel :title="t('messages.nexus.automation.title')">
            <div class="mb-4 flex items-center justify-between">
                <p class="text-sm text-nexus-text-muted">{{ t('messages.nexus.automation.how_it_works') }}</p>
                <a href="{{ route('nexus.automation.create') }}" class="rounded-md bg-nexus-cyan/20 px-3 py-1.5 text-sm font-semibold text-nexus-cyan hover:bg-nexus-cyan/30 shrink-0">
                    {{ t('messages.nexus.automation.create_new') }}
                </a>
            </div>

            @if (count($rules) === 0)
                <p class="text-sm text-nexus-text-muted">{{ t('messages.nexus.automation.empty') }}</p>
            @else
                <div class="space-y-3">
                    @foreach ($rules as $rule)
                        <div class="rounded-md border border-nexus-border bg-nexus-surface-1 p-4">
                            <div class="flex items-center justify-between">
                                <span class="font-mono text-sm text-nexus-text">{{ t('messages.nexus.automation.type.'.$rule->type) }}</span>
                                <x-status-badge :status="$rule->status === 'active' ? 'success' : 'warning'" :label="t('messages.nexus.automation.status.'.$rule->status)" />
                            </div>
                            <p class="mt-1 text-xs text-nexus-text-muted">
                                {{ t('messages.nexus.automation.last_triggered') }}:
                                {{ $rule->lastTriggeredAt ?? t('messages.nexus.automation.never') }}
                            </p>
                            <div class="mt-3 flex gap-2">
                                @if ($rule->status === 'active')
                                    <form method="POST" action="{{ route('nexus.automation.pause', $rule->id) }}">
                                        @csrf
                                        <button type="submit" class="rounded-md border border-nexus-border px-3 py-1 text-xs text-nexus-text hover:bg-nexus-surface-2">
                                            {{ t('messages.nexus.automation.pause') }}
                                        </button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('nexus.automation.resume', $rule->id) }}">
                                        @csrf
                                        <button type="submit" class="rounded-md border border-nexus-cyan/40 px-3 py-1 text-xs text-nexus-cyan hover:bg-nexus-cyan/10">
                                            {{ t('messages.nexus.automation.resume') }}
                                        </button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('nexus.automation.destroy', $rule->id) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-md border border-nexus-error/40 px-3 py-1 text-xs text-nexus-error hover:bg-nexus-error/10">
                                        {{ t('messages.nexus.automation.delete') }}
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

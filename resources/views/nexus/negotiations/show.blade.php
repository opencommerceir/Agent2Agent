@extends('nexus::layouts.app')

@section('title', t('messages.nexus.negotiation.show.title'))

@php
    $initialMessages = collect($messages)->map(fn ($m) => $m->toArray())->values()->all();
    $lastId = $initialMessages !== [] ? end($initialMessages)['id'] : 0;
    $otherPartyName = dashboard_language()->value === 'fa' ? $otherPartyNameFa : $otherPartyNameEn;
@endphp

@section('content')
    <div
        class="mx-auto max-w-3xl space-y-4"
        x-data="{
            messages: @js($initialMessages),
            lastId: {{ $lastId }},
            poll() {
                fetch('{{ route('nexus.negotiations.messages', $negotiation->id) }}?after=' + this.lastId)
                    .then(r => r.json())
                    .then(data => {
                        if (data.messages.length) {
                            this.messages.push(...data.messages);
                            this.lastId = data.messages[data.messages.length - 1].id;
                        }
                    });
            },
            init() {
                setInterval(() => this.poll(), 3000);
            },
        }"
    >
        <x-nexus-panel>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-nexus-text">{{ t('messages.nexus.negotiation.show.title') }} #{{ $negotiation->id }} — {{ t('messages.nexus.negotiation.show.with') }} {{ $otherPartyName }}</p>
                    <p class="text-xs text-nexus-text-muted">{{ t('messages.nexus.negotiation.show.round') }} {{ $negotiation->roundCount }}/{{ $negotiation->maxRounds }}</p>
                </div>
                <x-status-badge :status="$negotiation->status === 'accepted' ? 'success' : ($negotiation->status === 'rejected' ? 'error' : ($negotiation->status === 'pending_approval' ? 'warning' : 'idle'))" :label="t('messages.nexus.negotiation.status.'.$negotiation->status)" />
            </div>
        </x-nexus-panel>

        @if ($negotiation->status === 'pending_approval')
            <x-nexus-panel style="border-color: var(--color-nexus-warning)">
                <p class="mb-3 text-sm text-nexus-text">{{ t('messages.nexus.negotiation.show.pending_approval_banner') }}</p>
                <div class="flex gap-2">
                    <form method="POST" action="{{ route('nexus.negotiations.approve', $negotiation->id) }}">
                        @csrf
                        <button type="submit" class="rounded-md bg-nexus-success/20 px-4 py-1.5 text-sm font-semibold text-nexus-success hover:bg-nexus-success/30">
                            {{ t('messages.nexus.negotiation.show.approve') }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('nexus.negotiations.reject', $negotiation->id) }}">
                        @csrf
                        <button type="submit" class="rounded-md bg-nexus-error/20 px-4 py-1.5 text-sm font-semibold text-nexus-error hover:bg-nexus-error/30">
                            {{ t('messages.nexus.negotiation.show.reject') }}
                        </button>
                    </form>
                </div>
            </x-nexus-panel>
        @endif

        <x-nexus-panel>
            <template x-if="messages.length === 0">
                <p class="text-sm text-nexus-text-muted">{{ t('messages.nexus.negotiation.show.no_messages') }}</p>
            </template>

            <div class="space-y-3">
                <template x-for="message in messages" :key="message.id">
                    <div class="rounded-md border border-nexus-border bg-nexus-surface-1/60 p-3">
                        <div class="mb-1 flex items-center justify-between">
                            <span class="font-mono text-xs uppercase tracking-wide text-nexus-cyan" x-text="message.senderBusinessId === {{ $actingBusinessId }} ? '{{ addslashes(dashboard_language()->value === "fa" ? "شما" : "You") }}' : '{{ addslashes($otherPartyName) }}'"></span>
                            <span class="font-mono text-xs text-nexus-text-muted" x-text="message.type"></span>
                        </div>
                        <p class="text-sm text-nexus-text" x-text="(message.terms.priceAmount / 100).toLocaleString() + ' ' + message.terms.priceCurrency + ' × ' + message.terms.quantity"></p>
                        <template x-if="message.reasoning">
                            <div class="mt-2 rounded bg-nexus-bg/40 p-2 text-xs text-nexus-text-muted">
                                <p class="mb-1 font-semibold text-nexus-purple" x-text="'{{ t('messages.nexus.negotiation.show.reasoning') }}'"></p>
                                <template x-for="thought in (message.reasoning.thoughts || [])" :key="thought">
                                    <p x-text="'• ' + thought"></p>
                                </template>
                                <p class="mt-1" x-text="message.reasoning.explanation"></p>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </x-nexus-panel>
    </div>
@endsection

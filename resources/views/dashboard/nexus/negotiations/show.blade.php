@extends('layouts.dashboard')

@section('title', t('messages.nexus.admin.negotiations.title').' #'.$negotiation->id())

@php
    $lang = dashboard_language()->value;
    $initiatorName = $lang === 'fa' ? $initiatorNameFa : $initiatorNameEn;
    $counterpartyName = $lang === 'fa' ? $counterpartyNameFa : $counterpartyNameEn;
    $statusClasses = [
        'proposed' => 'bg-blue-50 text-blue-700',
        'countered' => 'bg-blue-50 text-blue-700',
        'pending_approval' => 'bg-amber-50 text-amber-700',
        'accepted' => 'bg-green-50 text-green-700',
        'rejected' => 'bg-red-50 text-red-700',
        'expired' => 'bg-gray-100 text-gray-500',
    ];
    $escrowStatusClasses = [
        'held' => 'bg-amber-50 text-amber-700',
        'released' => 'bg-green-50 text-green-700',
        'disputed' => 'bg-red-50 text-red-700',
        'refunded' => 'bg-gray-100 text-gray-500',
    ];
    $initialMessages = collect($messages)->map(fn ($m) => \App\Domains\Nexus\Negotiation\Application\DTOs\NegotiationMessageData::fromEntity($m)->toArray())->values()->all();
    $lastId = $initialMessages !== [] ? end($initialMessages)['id'] : 0;
@endphp

@section('content')
    <a href="{{ route('dashboard.nexus.negotiations.index') }}" class="mb-4 inline-block text-sm text-blue-600 hover:text-blue-800">&larr; {{ t('messages.nexus.admin.negotiations.back') }}</a>

    <div class="mb-4 flex items-center justify-between rounded-lg border border-gray-200 bg-white p-4">
        <div>
            <p class="text-sm font-medium">#{{ $negotiation->id() }} — {{ $initiatorName }} &harr; {{ $counterpartyName }}</p>
            <p class="text-xs text-gray-400">{{ t('messages.nexus.negotiation.index.round') }} {{ $negotiation->roundCount() }}/{{ $negotiation->maxRounds() }}</p>
        </div>
        <span class="rounded-full px-3 py-1 text-xs font-medium {{ $statusClasses[$negotiation->status()->value] ?? 'bg-gray-100 text-gray-500' }}">
            {{ t('messages.nexus.negotiation.status.'.$negotiation->status()->value) }}
        </span>
    </div>

    <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <h2 class="mb-2 text-sm font-semibold text-gray-700">{{ t('messages.nexus.admin.negotiations.contract.title') }}</h2>
            @if ($contract)
                <p class="text-xs text-gray-500">{{ t('messages.nexus.admin.negotiations.contract.hash') }}: <span class="font-mono">{{ substr($contract->contentHash(), 0, 16) }}&hellip;</span></p>
                <p class="text-xs text-gray-500">{{ t('messages.nexus.admin.negotiations.contract.signed_at') }}: {{ $contract->signedAt()->format('Y-m-d H:i') }}</p>
                <p class="text-xs text-gray-500">{{ $contract->pdfPath() ? t('messages.nexus.admin.negotiations.contract.pdf_generated') : t('messages.nexus.admin.negotiations.contract.pdf_none') }}</p>
            @else
                <p class="text-sm text-gray-500">{{ t('messages.nexus.admin.negotiations.contract.none') }}</p>
            @endif
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <h2 class="mb-2 text-sm font-semibold text-gray-700">{{ t('messages.nexus.negotiation.escrow.title') }}</h2>
            @if ($escrow)
                <div class="mb-1 flex items-center justify-between">
                    <p class="text-xs text-gray-500">{{ t('messages.nexus.negotiation.escrow.gross') }}: {{ number_format($escrow->grossAmount() / 100) }} {{ $escrow->currency() }}</p>
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $escrowStatusClasses[$escrow->status()->value] ?? 'bg-gray-100 text-gray-500' }}">
                        {{ t('messages.nexus.negotiation.escrow.status.'.$escrow->status()->value) }}
                    </span>
                </div>
                <p class="text-xs text-gray-500">{{ t('messages.nexus.negotiation.escrow.fee') }}: {{ number_format($escrow->platformFeeAmount() / 100) }} {{ $escrow->currency() }}</p>
                <p class="text-xs text-gray-500">{{ t('messages.nexus.negotiation.escrow.net') }}: {{ number_format($escrow->netAmount() / 100) }} {{ $escrow->currency() }}</p>
            @else
                <p class="text-sm text-gray-500">{{ t('messages.nexus.admin.negotiations.contract.none') }}</p>
            @endif
        </div>
    </div>

    <div
        class="rounded-lg border border-gray-200 bg-white p-4"
        x-data="{
            messages: @js($initialMessages),
            lastId: {{ $lastId }},
            poll() {
                fetch('{{ route('dashboard.nexus.negotiations.messages', $negotiation->id()) }}?after=' + this.lastId)
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
        <h2 class="mb-3 text-sm font-semibold text-gray-700">{{ t('messages.nexus.admin.negotiations.conversation') }}</h2>

        <template x-if="messages.length === 0">
            <p class="text-sm text-gray-500">{{ t('messages.nexus.negotiation.show.no_messages') }}</p>
        </template>

        <div class="space-y-3">
            <template x-for="message in messages" :key="message.id">
                <div class="rounded-md border border-gray-200 bg-gray-50 p-3">
                    <div class="mb-1 flex items-center justify-between">
                        <span
                            class="font-mono text-xs font-semibold uppercase tracking-wide text-blue-700"
                            x-text="message.senderBusinessId === {{ $negotiation->initiatorBusinessId() }} ? '{{ addslashes($initiatorName) }}' : '{{ addslashes($counterpartyName) }}'"
                        ></span>
                        <span class="font-mono text-xs text-gray-400" x-text="message.type"></span>
                    </div>
                    <p class="text-sm text-gray-700" x-text="(message.terms.priceAmount / 100).toLocaleString() + ' ' + message.terms.priceCurrency + ' × ' + message.terms.quantity"></p>
                    <template x-if="message.reasoning">
                        <div class="mt-2 rounded bg-white p-2 text-xs text-gray-500">
                            <p class="mb-1 font-semibold text-gray-600">{{ t('messages.nexus.negotiation.show.reasoning') }}</p>
                            <template x-for="thought in (message.reasoning.thoughts || [])" :key="thought">
                                <p x-text="'• ' + thought"></p>
                            </template>
                            <p class="mt-1" x-text="message.reasoning.explanation"></p>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>
@endsection

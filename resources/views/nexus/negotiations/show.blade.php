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
                    <p class="text-sm text-nexus-text">
                        {{ t('messages.nexus.negotiation.show.title') }} #{{ $negotiation->id }} — {{ t('messages.nexus.negotiation.show.with') }} {{ $otherPartyName }}
                        @if ($otherPartyReputation)
                            <span class="text-xs text-nexus-cyan">({{ $otherPartyReputation->score }}/1000@if ($otherPartyReputation->reviewCount > 0), {{ $otherPartyReputation->averageRating }}/5@endif)</span>
                        @endif
                    </p>
                    <p class="text-xs text-nexus-text-muted">{{ t('messages.nexus.negotiation.show.round') }} {{ $negotiation->roundCount }}/{{ $negotiation->maxRounds }}</p>
                </div>
                <x-status-badge :status="$negotiation->status === 'accepted' ? 'success' : ($negotiation->status === 'rejected' ? 'error' : ($negotiation->status === 'pending_approval' ? 'warning' : 'idle'))" :label="t('messages.nexus.negotiation.status.'.$negotiation->status)" />
            </div>
        </x-nexus-panel>

        @if ($escrow)
            <x-nexus-panel :title="t('messages.nexus.negotiation.escrow.title')">
                <div class="mb-3 flex items-center justify-between">
                    <div class="text-sm text-nexus-text-muted">
                        <p>{{ t('messages.nexus.negotiation.escrow.gross') }}: {{ number_format($escrow->grossAmount / 100) }} {{ $escrow->currency }}</p>
                        <p>{{ t('messages.nexus.negotiation.escrow.fee') }} ({{ $escrow->platformFeePercent }}%): {{ number_format($escrow->platformFeeAmount / 100) }} {{ $escrow->currency }}</p>
                        <p>{{ t('messages.nexus.negotiation.escrow.net') }}: {{ number_format($escrow->netAmount / 100) }} {{ $escrow->currency }}</p>
                    </div>
                    <x-status-badge :status="$escrow->status === 'held' ? 'warning' : ($escrow->status === 'released' ? 'success' : ($escrow->status === 'disputed' ? 'error' : 'idle'))" :label="t('messages.nexus.negotiation.escrow.status.'.$escrow->status)" />
                </div>

                @if ($escrow->status === 'held')
                    <div class="flex gap-2">
                        @if ($actingBusinessId === $negotiation->initiatorBusinessId)
                            <form method="POST" action="{{ route('nexus.negotiations.escrow.release', $negotiation->id) }}">
                                @csrf
                                <button type="submit" class="rounded-md bg-nexus-success/20 px-4 py-1.5 text-sm font-semibold text-nexus-success hover:bg-nexus-success/30">
                                    {{ t('messages.nexus.negotiation.escrow.confirm_delivery') }}
                                </button>
                            </form>
                        @else
                            <p class="text-xs text-nexus-text-muted">{{ t('messages.nexus.negotiation.escrow.release_awaiting_buyer') }}</p>
                        @endif
                        <form method="POST" action="{{ route('nexus.negotiations.escrow.dispute', $negotiation->id) }}">
                            @csrf
                            <button type="submit" class="rounded-md bg-nexus-error/20 px-4 py-1.5 text-sm font-semibold text-nexus-error hover:bg-nexus-error/30">
                                {{ t('messages.nexus.negotiation.escrow.dispute') }}
                            </button>
                        </form>
                    </div>
                @endif
            </x-nexus-panel>
        @endif

        @if ($disputeCase)
            <x-nexus-panel :title="t('messages.nexus.negotiation.dispute.title')">
                <p class="mb-2 text-xs text-nexus-text-muted">{{ t('messages.nexus.negotiation.dispute.status') }}: {{ t('messages.nexus.admin.disputes.status.'.$disputeCase->status()->value) }}</p>

                @if ($disputeCase->evidence() !== [])
                    <div class="mb-3 space-y-1 rounded-md bg-nexus-bg/40 p-2 text-xs text-nexus-text-muted">
                        @foreach ($disputeCase->evidence() as $entry)
                            <p>{{ $entry['businessId'] === $actingBusinessId ? t('messages.nexus.negotiation.dispute.you') : $otherPartyName }}: {{ $entry['note'] }}</p>
                        @endforeach
                    </div>
                @endif

                @if ($disputeCase->status()->value !== 'resolved')
                    <form method="POST" action="{{ route('nexus.negotiations.dispute.evidence', $negotiation->id) }}" class="space-y-2">
                        @csrf
                        <textarea name="note" rows="2" class="block w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-1.5 text-sm text-nexus-text" placeholder="{{ t('messages.nexus.negotiation.dispute.note_placeholder') }}"></textarea>
                        <button type="submit" class="rounded-md bg-nexus-cyan/20 px-4 py-1.5 text-sm font-semibold text-nexus-cyan hover:bg-nexus-cyan/30">
                            {{ t('messages.nexus.negotiation.dispute.submit_evidence') }}
                        </button>
                    </form>
                @endif
            </x-nexus-panel>
        @endif

        @if ($escrow && $escrow->status === 'released')
            <x-nexus-panel :title="t('messages.nexus.negotiation.review.title')">
                @if ($myReview)
                    <p class="text-sm text-nexus-text-muted">{{ t('messages.nexus.negotiation.review.already_submitted') }} ({{ $myReview->rating() }}/5)</p>
                @else
                    <form method="POST" action="{{ route('nexus.negotiations.review.submit', $negotiation->id) }}" class="space-y-2">
                        @csrf
                        <label class="block text-sm text-nexus-text-muted">
                            {{ t('messages.nexus.negotiation.review.rating') }}
                            <select name="rating" class="mt-1 block rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-1.5 text-sm text-nexus-text">
                                <option value="5">5</option>
                                <option value="4">4</option>
                                <option value="3">3</option>
                                <option value="2">2</option>
                                <option value="1">1</option>
                            </select>
                        </label>
                        <textarea name="comment" rows="2" class="block w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-1.5 text-sm text-nexus-text" placeholder="{{ t('messages.nexus.negotiation.review.comment_placeholder') }}"></textarea>
                        <button type="submit" class="rounded-md bg-nexus-cyan/20 px-4 py-1.5 text-sm font-semibold text-nexus-cyan hover:bg-nexus-cyan/30">
                            {{ t('messages.nexus.negotiation.review.submit') }}
                        </button>
                    </form>
                @endif
            </x-nexus-panel>
        @endif

        @if ($negotiation->status === 'pending_approval')
            <x-nexus-panel style="border-color: var(--color-nexus-warning)">
                @if ($approvalRequest)
                    <p class="mb-1 text-xs text-nexus-text-muted">
                        {{ t('messages.nexus.negotiation.show.approval_chain_level') }}
                        {{ $approvalRequest->currentLevelIndex + 1 }}/{{ count($approvalRequest->requiredLevels) }}
                        — {{ t('messages.nexus.business.team.role_option.'.($approvalRequest->currentRequiredRole ?: $approvalRequest->requiredLevels[$approvalRequest->currentLevelIndex]['role'])) }}
                    </p>
                    @if ($negotiation->pendingApprovalBusinessId === $actingBusinessId && $callingOwnerRole === $approvalRequest->currentRequiredRole)
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
                    @elseif ($negotiation->pendingApprovalBusinessId === $actingBusinessId)
                        <p class="text-sm text-nexus-text">{{ t('messages.nexus.negotiation.show.pending_approval_waiting_other_role') }}</p>
                    @else
                        <p class="text-sm text-nexus-text">{{ t('messages.nexus.negotiation.show.pending_approval_waiting') }}</p>
                    @endif
                @elseif ($negotiation->pendingApprovalBusinessId === $actingBusinessId)
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
                @else
                    <p class="text-sm text-nexus-text">{{ t('messages.nexus.negotiation.show.pending_approval_waiting') }}</p>
                @endif
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

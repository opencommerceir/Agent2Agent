@extends('nexus::layouts.app')

@section('title', $marketplace->nameEn)

@section('content')
    <div
        class="mx-auto max-w-4xl space-y-4"
        @if ($marketplace->brandingPrimaryColor) style="--brand: {{ $marketplace->brandingPrimaryColor }}" @endif
    >
        <x-nexus-panel>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-nexus-text" @if ($marketplace->brandingPrimaryColor) style="color: {{ $marketplace->brandingPrimaryColor }}" @endif>{{ $marketplace->nameEn }}</p>
                    <p class="text-xs text-nexus-text-muted">{{ $marketplace->ownerBusinessNameEn }}</p>
                </div>
                @if ($isOwner && $marketplace->status === 'active')
                    <form method="POST" action="{{ route('nexus.private-marketplace.archive', $marketplace->id) }}">
                        @csrf
                        <button type="submit" class="rounded-md border border-nexus-error/40 px-3 py-1.5 text-sm text-nexus-error hover:bg-nexus-error/10">
                            {{ t('messages.nexus.private_marketplace.archive') }}
                        </button>
                    </form>
                @endif
            </div>
        </x-nexus-panel>

        @if ($isOwner)
            <x-nexus-panel :title="t('messages.nexus.private_marketplace.invite.submit')">
                <form method="POST" action="{{ route('nexus.private-marketplace.invite', $marketplace->id) }}" class="flex items-end gap-3">
                    @csrf
                    <div class="flex-1">
                        <label class="mb-1 block text-sm text-nexus-text">{{ t('messages.nexus.private_marketplace.invite.target_business_id') }}</label>
                        <input type="number" name="target_business_id" required class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text focus:border-nexus-cyan focus:outline-none">
                    </div>
                    <button type="submit" class="rounded-md bg-nexus-cyan/20 px-4 py-2 text-sm font-semibold text-nexus-cyan hover:bg-nexus-cyan/30">
                        {{ t('messages.nexus.private_marketplace.invite.submit') }}
                    </button>
                </form>
            </x-nexus-panel>
        @endif

        <x-nexus-panel :title="t('messages.nexus.private_marketplace.members.title')">
            @if (count($marketplace->members) === 0)
                <p class="text-sm text-nexus-text-muted">{{ t('messages.nexus.private_marketplace.members.empty') }}</p>
            @else
                <div class="space-y-2">
                    @foreach ($marketplace->members as $member)
                        <div class="flex items-center justify-between rounded-md border border-nexus-border bg-nexus-surface-1 p-3">
                            <span class="text-sm text-nexus-text">{{ $member['nameEn'] }}</span>
                            <div class="flex items-center gap-2">
                                <x-status-badge :status="$member['status'] === 'active' ? 'success' : ($member['status'] === 'invited' ? 'warning' : 'error')" :label="t('messages.nexus.holding.subsidiaries.status.'.$member['status'])" />
                                @if ($isOwner && $member['status'] !== 'removed')
                                    <form method="POST" action="{{ route('nexus.private-marketplace.members.remove', [$marketplace->id, $member['id']]) }}">
                                        @csrf
                                        <button type="submit" class="rounded-md border border-nexus-error/40 px-2 py-1 text-xs text-nexus-error hover:bg-nexus-error/10">
                                            {{ t('messages.nexus.holding.subsidiaries.remove') }}
                                        </button>
                                    </form>
                                @elseif (! $isOwner && $member['businessId'] === $businessId && $member['status'] === 'active')
                                    <form method="POST" action="{{ route('nexus.private-marketplace.members.leave', $member['id']) }}">
                                        @csrf
                                        <button type="submit" class="rounded-md border border-nexus-error/40 px-2 py-1 text-xs text-nexus-error hover:bg-nexus-error/10">
                                            {{ t('messages.nexus.holding.subsidiaries.leave') }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-nexus-panel>

        <x-nexus-panel :title="t('messages.nexus.private_marketplace.listings.title')">
            <form method="POST" action="{{ route('nexus.private-marketplace.listings.store', $marketplace->id) }}" class="mb-4 grid gap-3 sm:grid-cols-5">
                @csrf
                <select name="catalog_item_type" required class="rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text">
                    @foreach ($catalogItemTypes as $type)
                        <option value="{{ $type->value }}">{{ $type->value }}</option>
                    @endforeach
                </select>
                <input type="number" name="catalog_item_id" placeholder="{{ t('messages.nexus.private_marketplace.listings.item_id') }}" required class="rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text">
                <input type="number" name="special_price_amount" placeholder="{{ t('messages.nexus.private_marketplace.listings.price') }}" required class="rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text">
                <input type="text" name="special_price_currency" value="IRT" maxlength="3" required class="rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text">
                <button type="submit" class="rounded-md bg-nexus-cyan/20 px-4 py-2 text-sm font-semibold text-nexus-cyan hover:bg-nexus-cyan/30">
                    {{ t('messages.nexus.private_marketplace.listings.add') }}
                </button>
            </form>

            @if (count($listings) === 0)
                <p class="text-sm text-nexus-text-muted">{{ t('messages.nexus.private_marketplace.listings.empty') }}</p>
            @else
                <div class="space-y-2">
                    @foreach ($listings as $listing)
                        <div class="flex items-center justify-between rounded-md border border-nexus-border bg-nexus-surface-1 p-3">
                            <span class="font-mono text-sm text-nexus-text">{{ $listing['listingBusinessNameEn'] }} — {{ $listing['catalogItemType'] }} #{{ $listing['catalogItemId'] }}</span>
                            <span class="text-sm text-nexus-cyan">{{ number_format($listing['specialPriceAmount']) }} {{ $listing['specialPriceCurrency'] }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-nexus-panel>
    </div>
@endsection

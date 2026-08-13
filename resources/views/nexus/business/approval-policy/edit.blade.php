@extends('nexus::layouts.app')

@section('title', t('messages.nexus.business.approval_policy.title'))

@php
    $existingLevels = $policy?->levels ?? [];
    $rowCount = max(count($existingLevels), 1) + 1;
@endphp

@section('content')
    <div class="mx-auto max-w-2xl">
        <x-nexus-panel :title="t('messages.nexus.business.approval_policy.title')">
            <p class="mb-4 text-sm text-nexus-text-muted">{{ t('messages.nexus.business.approval_policy.description') }}</p>

            @if (session('status'))
                <div class="mb-4 rounded-md border border-nexus-success/40 bg-nexus-success/10 px-4 py-2 text-sm text-nexus-success">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-md border border-nexus-error/40 bg-nexus-error/10 px-4 py-3 text-sm text-nexus-error">
                    <ul class="list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('nexus.business.approval-policy.update') }}" class="space-y-3">
                @csrf

                @for ($i = 0; $i < $rowCount; $i++)
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-sm text-nexus-text">{{ t('messages.nexus.business.approval_policy.role') }}</label>
                            <select name="levels[{{ $i }}][role]" class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text focus:border-nexus-cyan focus:outline-none">
                                <option value="">{{ t('messages.nexus.business.approval_policy.unused') }}</option>
                                @foreach ($roles as $role)
                                    <option value="{{ $role->value }}" @selected(($existingLevels[$i]['role'] ?? null) === $role->value)>{{ t('messages.nexus.business.team.role_option.'.$role->value) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-nexus-text">{{ t('messages.nexus.business.approval_policy.min_amount') }}</label>
                            <input type="number" name="levels[{{ $i }}][min_amount]" min="0" value="{{ $existingLevels[$i]['minAmount'] ?? '' }}" class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text focus:border-nexus-cyan focus:outline-none">
                        </div>
                    </div>
                @endfor

                <p class="text-xs text-nexus-text-muted">{{ t('messages.nexus.business.approval_policy.hint') }}</p>

                <button type="submit" class="w-full rounded-md bg-nexus-cyan/20 px-4 py-2 text-sm font-semibold text-nexus-cyan hover:bg-nexus-cyan/30">
                    {{ t('messages.nexus.business.approval_policy.submit') }}
                </button>
            </form>
        </x-nexus-panel>
    </div>
@endsection

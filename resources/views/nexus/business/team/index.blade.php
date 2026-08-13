@extends('nexus::layouts.app')

@section('title', t('messages.nexus.business.team.title'))

@section('content')
    <div class="mx-auto max-w-3xl space-y-4">
        <x-nexus-panel :title="t('messages.nexus.business.team.invite')">
            @if ($errors->any())
                <div class="mb-4 rounded-md border border-nexus-error/40 bg-nexus-error/10 px-4 py-3 text-sm text-nexus-error">
                    <ul class="list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('nexus.business.team.store') }}" class="grid gap-3 sm:grid-cols-4">
                @csrf
                <div class="sm:col-span-1">
                    <label class="mb-1 block text-sm text-nexus-text">{{ t('messages.nexus.business.team.name') }}</label>
                    <input type="text" name="name" required class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text focus:border-nexus-cyan focus:outline-none">
                </div>
                <div class="sm:col-span-1">
                    <label class="mb-1 block text-sm text-nexus-text">{{ t('messages.nexus.business.team.email') }}</label>
                    <input type="email" name="email" required class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text focus:border-nexus-cyan focus:outline-none">
                </div>
                <div class="sm:col-span-1">
                    <label class="mb-1 block text-sm text-nexus-text">{{ t('messages.nexus.business.team.role') }}</label>
                    <select name="role" required class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text focus:border-nexus-cyan focus:outline-none">
                        @foreach ($roles as $role)
                            <option value="{{ $role->value }}">{{ t('messages.nexus.business.team.role_option.'.$role->value) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end sm:col-span-1">
                    <button type="submit" class="w-full rounded-md bg-nexus-cyan/20 px-4 py-2 text-sm font-semibold text-nexus-cyan hover:bg-nexus-cyan/30">
                        {{ t('messages.nexus.business.team.submit') }}
                    </button>
                </div>
            </form>
        </x-nexus-panel>

        <x-nexus-panel :title="t('messages.nexus.business.team.title')">
            <div class="space-y-3">
                @foreach ($members as $member)
                    <div class="flex items-center justify-between rounded-md border border-nexus-border bg-nexus-surface-1 p-4">
                        <div>
                            <p class="text-sm text-nexus-text">{{ $member->name }} — {{ $member->email }}</p>
                            <div class="mt-1 flex items-center gap-2">
                                <x-status-badge status="info" :label="t('messages.nexus.business.team.role_option.'.$member->role)" />
                                @if ($member->mustChangePassword)
                                    <x-status-badge status="warning" :label="t('messages.nexus.business.team.must_change_password')" />
                                @endif
                            </div>
                        </div>
                        @if ($member->id !== $callingOwnerId)
                            <div class="flex items-center gap-2">
                                <form method="POST" action="{{ route('nexus.business.team.role.update', $member->id) }}" class="flex items-center gap-2">
                                    @csrf
                                    <select name="role" onchange="this.form.submit()" class="rounded-md border border-nexus-border bg-nexus-surface-1 px-2 py-1 text-xs text-nexus-text">
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->value }}" @selected($role->value === $member->role)>{{ t('messages.nexus.business.team.role_option.'.$role->value) }}</option>
                                        @endforeach
                                    </select>
                                </form>
                                <form method="POST" action="{{ route('nexus.business.team.destroy', $member->id) }}">
                                    @csrf
                                    <button type="submit" class="rounded-md border border-nexus-error/40 px-3 py-1.5 text-sm text-nexus-error hover:bg-nexus-error/10">
                                        {{ t('messages.nexus.business.team.remove') }}
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-nexus-panel>
    </div>
@endsection

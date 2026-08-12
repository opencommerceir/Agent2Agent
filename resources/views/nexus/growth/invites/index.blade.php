@extends('nexus::layouts.app')

@section('title', t('messages.nexus.growth.invites.title'))

@section('content')
    <div class="mx-auto max-w-3xl space-y-4">
        <x-nexus-panel :title="t('messages.nexus.growth.invites.title')">
            @if (session('status'))
                <div class="mb-4 rounded-md border border-nexus-success/40 bg-nexus-success/10 px-4 py-3 text-sm text-nexus-success">
                    {{ session('status') }}
                </div>
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

            <form method="POST" action="{{ route('nexus.growth.invites.store') }}" class="mb-6 flex flex-col gap-3 sm:flex-row">
                @csrf
                <input type="text" name="invitee_name" placeholder="{{ t('messages.nexus.growth.invites.name_placeholder') }}" required
                    class="flex-1 rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text focus:border-nexus-cyan focus:outline-none">
                <input type="email" name="invitee_email" placeholder="{{ t('messages.nexus.growth.invites.email_placeholder') }}" required
                    class="flex-1 rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text focus:border-nexus-cyan focus:outline-none">
                <button type="submit" class="rounded-md bg-nexus-cyan/20 px-4 py-2 text-sm font-semibold text-nexus-cyan hover:bg-nexus-cyan/30">
                    {{ t('messages.nexus.growth.invites.submit') }}
                </button>
            </form>

            @if (count($invites) === 0)
                <p class="text-sm text-nexus-text-muted">{{ t('messages.nexus.growth.invites.empty') }}</p>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-nexus-border text-start text-nexus-text-muted">
                            <th class="py-2 text-start">{{ t('messages.nexus.growth.invites.table_name') }}</th>
                            <th class="py-2 text-start">{{ t('messages.nexus.growth.invites.table_email') }}</th>
                            <th class="py-2 text-start">{{ t('messages.nexus.growth.invites.table_status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invites as $invite)
                            <tr class="border-b border-nexus-border/50">
                                <td class="py-2 text-nexus-text">{{ $invite->inviteeName }}</td>
                                <td class="py-2 text-nexus-text-muted">{{ $invite->inviteeEmail }}</td>
                                <td class="py-2">
                                    <x-status-badge :status="$invite->status === 'converted' ? 'success' : 'idle'" :label="t('messages.nexus.growth.invites.status.'.$invite->status)" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-nexus-panel>
    </div>
@endsection

@extends('layouts.dashboard')

@section('title', t('messages.nexus.admin.sso_providers.title'))

@section('content')
    <h1 class="mb-2 text-xl font-semibold">{{ t('messages.nexus.admin.sso_providers.title') }}</h1>
    <p class="mb-6 max-w-2xl text-sm text-gray-500">{{ t('messages.nexus.admin.sso_providers.stub_note') }}</p>

    <div class="max-w-2xl overflow-hidden rounded-lg border border-gray-200 bg-white">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-2">{{ t('messages.nexus.admin.sso_providers.provider') }}</th>
                    <th class="px-4 py-2">{{ t('messages.nexus.admin.sso_providers.interactive_login') }}</th>
                    <th class="px-4 py-2">{{ t('messages.nexus.admin.sso_providers.configured') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($providers as $provider)
                    <tr class="border-t border-gray-100">
                        <td class="px-4 py-2 font-medium">{{ ucfirst($provider['key']) }}</td>
                        <td class="px-4 py-2">
                            <span class="{{ $provider['supportsInteractiveLogin'] ? 'text-green-700' : 'text-gray-400' }}">
                                {{ $provider['supportsInteractiveLogin'] ? t('messages.nexus.admin.sso_providers.yes') : t('messages.nexus.admin.sso_providers.no') }}
                            </span>
                        </td>
                        <td class="px-4 py-2">
                            <span class="{{ $provider['isConfigured'] ? 'text-green-700' : 'text-gray-400' }}">
                                {{ $provider['isConfigured'] ? t('messages.nexus.admin.sso_providers.yes') : t('messages.nexus.admin.sso_providers.no') }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection

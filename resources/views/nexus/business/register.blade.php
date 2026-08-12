@extends('nexus::layouts.app')

@section('title', t('messages.nexus.business.register.title'))

@section('content')
    <div class="mx-auto max-w-2xl">
        <x-nexus-panel :title="t('messages.nexus.business.register.title')">
            @if ($errors->any())
                <div class="mb-4 rounded-md border border-nexus-error/40 bg-nexus-error/10 px-4 py-3 text-sm text-nexus-error">
                    <ul class="list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('nexus.business.register.store') }}" enctype="multipart/form-data" class="space-y-6">
                @csrf
                <input type="hidden" name="referral_code" value="{{ old('referral_code', $referralCode ?? '') }}">

                @if ($referralCode ?? old('referral_code'))
                    <div class="rounded-md border border-nexus-purple/40 bg-nexus-purple/10 px-4 py-3 text-sm text-nexus-purple">
                        {{ t('messages.nexus.business.register.referred_by', ['code' => $referralCode ?? old('referral_code')]) }}
                    </div>
                @endif

                <fieldset class="space-y-3">
                    <legend class="text-sm font-semibold uppercase tracking-wide text-nexus-cyan">{{ t('messages.nexus.business.register.owner_section') }}</legend>

                    <div>
                        <label class="mb-1 block text-sm text-nexus-text">{{ t('messages.nexus.business.register.owner_name') }}</label>
                        <input type="text" name="owner_name" value="{{ old('owner_name') }}" required class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text focus:border-nexus-cyan focus:outline-none">
                    </div>

                    <div>
                        <label class="mb-1 block text-sm text-nexus-text">{{ t('messages.nexus.business.register.email') }}</label>
                        <input type="email" name="email" value="{{ old('email') }}" required class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text focus:border-nexus-cyan focus:outline-none">
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm text-nexus-text">{{ t('messages.nexus.business.register.password') }}</label>
                            <input type="password" name="password" required class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text focus:border-nexus-cyan focus:outline-none">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-nexus-text">{{ t('messages.nexus.business.register.password_confirmation') }}</label>
                            <input type="password" name="password_confirmation" required class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text focus:border-nexus-cyan focus:outline-none">
                        </div>
                    </div>
                </fieldset>

                <fieldset class="space-y-3">
                    <legend class="text-sm font-semibold uppercase tracking-wide text-nexus-cyan">{{ t('messages.nexus.business.register.business_section') }}</legend>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm text-nexus-text">{{ t('messages.nexus.business.register.name_fa') }}</label>
                            <input type="text" name="name_fa" dir="rtl" value="{{ old('name_fa') }}" required class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text focus:border-nexus-cyan focus:outline-none">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-nexus-text">{{ t('messages.nexus.business.register.name_en') }}</label>
                            <input type="text" name="name_en" dir="ltr" value="{{ old('name_en') }}" required class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text focus:border-nexus-cyan focus:outline-none">
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm text-nexus-text">{{ t('messages.nexus.business.register.type') }}</label>
                            <select name="type" required class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text focus:border-nexus-cyan focus:outline-none">
                                @foreach ($types as $type)
                                    <option value="{{ $type->value }}" @selected(old('type') === $type->value)>{{ t("messages.nexus.business.type.{$type->value}") }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-nexus-text">{{ t('messages.nexus.business.register.industry') }}</label>
                            <select name="industry" required class="w-full rounded-md border border-nexus-border bg-nexus-surface-1 px-3 py-2 text-sm text-nexus-text focus:border-nexus-cyan focus:outline-none">
                                @foreach ($industries as $industry)
                                    <option value="{{ $industry->value }}" @selected(old('industry') === $industry->value)>{{ t("messages.nexus.business.industry.{$industry->value}") }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm text-nexus-text">{{ t('messages.nexus.business.register.logo') }}</label>
                        <input type="file" name="logo" accept="image/*" class="w-full text-sm text-nexus-text">
                    </div>

                    <div>
                        <label class="mb-1 block text-sm text-nexus-text">{{ t('messages.nexus.business.register.documents') }}</label>
                        <input type="file" name="documents[]" multiple class="w-full text-sm text-nexus-text">
                    </div>
                </fieldset>

                <button type="submit" class="w-full rounded-md bg-nexus-cyan/20 px-4 py-2 text-sm font-semibold text-nexus-cyan hover:bg-nexus-cyan/30">
                    {{ t('messages.nexus.business.register.submit') }}
                </button>
            </form>

            <p class="mt-4 text-center text-sm text-nexus-text-muted">
                {{ t('messages.nexus.business.register.have_account') }}
                <a href="{{ route('nexus.business.login') }}" class="text-nexus-cyan hover:underline">{{ t('messages.nexus.business.login.submit') }}</a>
            </p>
        </x-nexus-panel>
    </div>
@endsection

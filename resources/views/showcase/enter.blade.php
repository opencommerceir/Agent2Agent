<!DOCTYPE html>
<html lang="{{ dashboard_language()->value }}" dir="{{ dashboard_language()->value === 'fa' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ t('showcase.gate.title') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-900 antialiased">
    <div class="mx-auto flex min-h-screen max-w-sm flex-col items-center justify-center px-6">
        <div class="w-full rounded-2xl border border-gray-200 bg-white p-8 shadow-sm">
            <h1 class="mb-1 text-center text-lg font-semibold">{{ t('showcase.gate.title') }}</h1>
            <p class="mb-6 text-center text-sm text-gray-500">{{ t('showcase.gate.subtitle') }}</p>

            @if ($errors->any())
                <div class="mb-4 rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-800">
                    {{ $errors->first('passcode') }}
                </div>
            @endif

            <form method="POST" action="{{ route('showcase.enter.store') }}" class="space-y-3">
                @csrf
                <input
                    type="password"
                    name="passcode"
                    autofocus
                    placeholder="{{ t('showcase.gate.placeholder') }}"
                    class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
                >
                <button
                    type="submit"
                    class="w-full rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700"
                >
                    {{ t('showcase.gate.submit') }}
                </button>
            </form>
        </div>
    </div>
</body>
</html>

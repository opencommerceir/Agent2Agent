<!DOCTYPE html>
<html lang="{{ dashboard_language()->value }}" dir="{{ dashboard_language()->value === 'fa' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ t('messages.auth.login_title') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-gray-50 text-gray-900 antialiased">
    <div class="w-full max-w-sm rounded-lg border border-gray-200 bg-white p-8 shadow-sm">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-lg font-semibold">{{ t('messages.auth.login_title') }}</h1>
            <div class="flex overflow-hidden rounded-md border border-gray-300 text-xs">
                <a href="{{ route('language.switch', 'en') }}" class="px-2 py-1 {{ dashboard_language()->value === 'en' ? 'bg-blue-600 text-white' : '' }}">EN</a>
                <a href="{{ route('language.switch', 'fa') }}" class="px-2 py-1 {{ dashboard_language()->value === 'fa' ? 'bg-blue-600 text-white' : '' }}">FA</a>
            </div>
        </div>

        @if ($errors->any())
            <div class="mb-4 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
            @csrf

            <div>
                <label for="email" class="mb-1 block text-sm font-medium">{{ t('messages.auth.email') }}</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
            </div>

            <div>
                <label for="password" class="mb-1 block text-sm font-medium">{{ t('messages.auth.password') }}</label>
                <input id="password" type="password" name="password" required
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="remember" value="1">
                {{ t('messages.auth.remember_me') }}
            </label>

            <button type="submit" class="w-full rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                {{ t('messages.auth.login_button') }}
            </button>
        </form>
    </div>
</body>
</html>

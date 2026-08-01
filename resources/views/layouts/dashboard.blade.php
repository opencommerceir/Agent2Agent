<!DOCTYPE html>
<html lang="{{ dashboard_language()->value }}" dir="{{ dashboard_language()->value === 'fa' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', t('messages.dashboard.title')) - {{ t('messages.dashboard.title') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-900 antialiased" x-data="{ sidebarOpen: false }">
    <div class="flex min-h-screen">
        {{-- Sidebar --}}
        <aside
            class="fixed inset-y-0 start-0 z-30 w-64 shrink-0 border-e border-gray-200 bg-white transition-transform md:static md:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full rtl:translate-x-full'"
        >
            <div class="flex h-16 items-center border-b border-gray-200 px-4 text-lg font-semibold">
                OpenCommerce
            </div>
            <nav class="space-y-1 p-3 text-sm">
                <a href="{{ route('dashboard.index') }}" class="block rounded-md px-3 py-2 hover:bg-gray-100 {{ request()->routeIs('dashboard.index') ? 'bg-gray-100 font-medium' : '' }}">{{ t('messages.nav.dashboard') }}</a>
                <a href="{{ route('dashboard.tenants.index') }}" class="block rounded-md px-3 py-2 hover:bg-gray-100 {{ request()->routeIs('dashboard.tenants.*') ? 'bg-gray-100 font-medium' : '' }}">{{ t('messages.nav.tenants') }}</a>
                <a href="{{ route('dashboard.agents.index') }}" class="block rounded-md px-3 py-2 hover:bg-gray-100 {{ request()->routeIs('dashboard.agents.*') ? 'bg-gray-100 font-medium' : '' }}">{{ t('messages.nav.agents') }}</a>
                <a href="{{ route('dashboard.products.index') }}" class="block rounded-md px-3 py-2 hover:bg-gray-100 {{ request()->routeIs('dashboard.products.*') ? 'bg-gray-100 font-medium' : '' }}">{{ t('messages.nav.products') }}</a>
                <a href="{{ route('dashboard.orders.index') }}" class="block rounded-md px-3 py-2 hover:bg-gray-100 {{ request()->routeIs('dashboard.orders.*') ? 'bg-gray-100 font-medium' : '' }}">{{ t('messages.nav.orders') }}</a>
                <a href="{{ route('dashboard.notifications.index') }}" class="block rounded-md px-3 py-2 hover:bg-gray-100 {{ request()->routeIs('dashboard.notifications.*') ? 'bg-gray-100 font-medium' : '' }}">{{ t('messages.nav.notifications') }}</a>
                <a href="{{ route('dashboard.analytics.index') }}" class="block rounded-md px-3 py-2 hover:bg-gray-100 {{ request()->routeIs('dashboard.analytics.*') ? 'bg-gray-100 font-medium' : '' }}">{{ t('messages.nav.analytics') }}</a>
                <a href="{{ route('dashboard.performance.index') }}" class="block rounded-md px-3 py-2 hover:bg-gray-100 {{ request()->routeIs('dashboard.performance.*') ? 'bg-gray-100 font-medium' : '' }}">{{ t('messages.nav.performance') }}</a>
                <a href="{{ route('dashboard.settings.index') }}" class="block rounded-md px-3 py-2 hover:bg-gray-100 {{ request()->routeIs('dashboard.settings.*') ? 'bg-gray-100 font-medium' : '' }}">{{ t('messages.nav.settings') }}</a>
            </nav>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            {{-- Navbar --}}
            <header class="flex h-16 items-center justify-between border-b border-gray-200 bg-white px-4">
                <button type="button" class="rounded-md p-2 hover:bg-gray-100 md:hidden" @click="sidebarOpen = !sidebarOpen" aria-label="Toggle sidebar">
                    <span>&#9776;</span>
                </button>

                <div class="hidden text-sm text-gray-500 md:block">
                    @auth
                        {{ t('messages.dashboard.welcome', ['name' => auth()->user()->name]) }}
                    @endauth
                </div>

                <div class="flex items-center gap-3">
                    <div class="flex overflow-hidden rounded-md border border-gray-300 text-xs" x-data="{ language: '{{ dashboard_language()->value }}' }">
                        <a href="{{ route('language.switch', 'en') }}" @click="language = 'en'" :class="language === 'en' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700'" class="px-2 py-1">EN</a>
                        <a href="{{ route('language.switch', 'fa') }}" @click="language = 'fa'" :class="language === 'fa' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700'" class="px-2 py-1">FA</a>
                    </div>

                    @auth
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-100">{{ t('messages.nav.logout') }}</button>
                        </form>
                    @endauth
                </div>
            </header>

            <main class="flex-1 p-6">
                @if (session('status'))
                    <div class="mb-4 rounded-md border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-4 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
                        <ul class="list-inside list-disc">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>

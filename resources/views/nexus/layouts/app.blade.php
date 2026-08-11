<!DOCTYPE html>
<html lang="{{ dashboard_language()->value }}" dir="{{ dashboard_language()->value === 'fa' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="{{ config('nexus.platform.theme.background') }}">
    <title>@yield('title', 'Nexus') - Nexus</title>
    @vite(['resources/css/nexus.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-nexus-bg font-sans text-slate-100 antialiased" x-data="{ sidebarOpen: false }">
    <div class="flex min-h-screen flex-col">
        <header class="flex h-16 items-center justify-between border-b border-nexus-border px-4 sm:px-6">
            <div class="flex items-center gap-2 text-lg font-semibold nexus-glow-text">
                <span class="inline-block size-2 rounded-full bg-nexus-cyan"></span>
                Nexus
            </div>

            <div class="flex items-center gap-3">
                <div class="flex overflow-hidden rounded-md border border-nexus-border text-xs" x-data="{ language: '{{ dashboard_language()->value }}' }">
                    <a href="{{ route('language.switch', 'en') }}" @click="language = 'en'" :class="language === 'en' ? 'bg-nexus-cyan/20 text-nexus-cyan' : 'text-slate-400'" class="px-2 py-1">EN</a>
                    <a href="{{ route('language.switch', 'fa') }}" @click="language = 'fa'" :class="language === 'fa' ? 'bg-nexus-cyan/20 text-nexus-cyan' : 'text-slate-400'" class="px-2 py-1">FA</a>
                </div>
            </div>
        </header>

        <main class="flex-1 p-4 sm:p-6">
            @yield('content')
        </main>
    </div>
</body>
</html>

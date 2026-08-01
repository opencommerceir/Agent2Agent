<?php

use App\Core\Domain\Services\TranslationServiceInterface;
use App\Core\Domain\ValueObjects\Language;

if (! function_exists('dashboard_language')) {
    /**
     * The Admin Dashboard's own current Language — a plain PHP session
     * key (`dashboard_language`), not the MCP Gateway's per-request
     * query/header detection (LanguageDetector's own priority chain has
     * nothing to detect from once a human has picked a language and it's
     * stored for their session). Defaults to English until the language
     * switcher route is visited at least once.
     */
    function dashboard_language(): Language
    {
        return Language::tryFrom(session('dashboard_language', 'en')) ?? Language::English;
    }
}

if (! function_exists('t')) {
    /**
     * The Blade-facing translation helper — a thin wrapper around
     * TranslationServiceInterface resolved against the Dashboard's own
     * current session language, the same role Laravel's own `__()` plays
     * for its native translator. Lives here (not inside any Domain/
     * Application class) because it reads the HTTP session, a framework
     * concern no Core class is allowed to touch (Domain Services Rules).
     *
     * @param array<string, scalar> $replace
     */
    function t(string $key, array $replace = []): string
    {
        return app(TranslationServiceInterface::class)->translate($key, dashboard_language(), $replace);
    }
}

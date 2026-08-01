<?php

namespace App\Http\Controllers;

use App\Core\Domain\ValueObjects\Language;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The Dashboard's own language switcher — stores the pick in the session
 * (`dashboard_language`, `dashboard_language()`'s own key) and redirects
 * back to whatever page the switcher was clicked from, so choosing a
 * language never navigates the User away from where they were. An
 * unsupported `$code` is silently ignored rather than a 404/500 — the
 * request never re-renders in a different language, which is a harmless
 * no-op, not an error worth surfacing.
 */
class LanguageController
{
    public function __invoke(Request $request, string $code): RedirectResponse
    {
        if (Language::tryFrom($code) !== null) {
            $request->session()->put('dashboard_language', $code);
        }

        return redirect()->back();
    }
}

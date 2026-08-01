<?php

namespace App\Core\Domain\Services;

use App\Core\Domain\ValueObjects\Language;

/**
 * The Domain-owned contract for "look up a translated string" — every
 * module that needs a translated message (MCPExceptionHandler,
 * Notifications' template lookups, a future Dashboard) depends on this,
 * never on the concrete Application\Services\TranslationService directly.
 *
 * Deliberately only exposes translate(): string, not the richer
 * TranslationData-returning resolve() the concrete TranslationService also
 * has. TranslationData lives in Application/DTOs (Application layer) —
 * putting it on this Domain interface would make Domain depend on
 * Application, the exact violation Phase 2's own gotcha (HANDOFF §7.2,
 * "Core must never import App\Modules") already taught this codebase to
 * catch before shipping. A caller that genuinely needs the
 * fallback-occurred diagnostic (this stage's own fallback tests) type-hints
 * the concrete Application\Services\TranslationService instead — that's an
 * Application-layer-to-Application-layer dependency, not a Domain one.
 *
 * $key is "{group}.{dot.path}", e.g. "errors.not_found" or
 * "messages.dashboard.title" — the first segment selects which
 * lang/{code}/{group}.json file to read.
 */
interface TranslationServiceInterface
{
    /**
     * @param array<string, scalar> $replace ":placeholder" => replacement, same convention as Laravel's own __().
     */
    public function translate(string $key, Language $language, array $replace = []): string;
}

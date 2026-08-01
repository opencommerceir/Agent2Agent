<?php

namespace App\Core\Domain\Services;

use App\Core\Domain\ValueObjects\ApiVersion;

/**
 * Pure priority-chain decision: given three already-extracted candidate
 * version strings, which ApiVersion is actually in effect for this
 * request. Deliberately framework-free (three nullable strings, not an
 * Illuminate\Http\Request) — the same "Domain contract exposes only the
 * pure decision, a Request-touching convenience lives on the concrete
 * Application-layer class instead" split TranslationServiceInterface
 * established for TranslationService's own richer resolve() method.
 *
 * Priority, confirmed during Stage 7 planning: the URL path segment always
 * wins when present — an Agent that explicitly hits /mcp/v1/execute always
 * gets v1's response shape, full stop, even if some intermediary attached
 * an Accept header naming a different version. Silently honoring a header
 * over an explicit URL would mean a v1 integration's response shape could
 * change out from under it without any code on its own end changing —
 * exactly the hidden-behavior failure mode this whole versioning system
 * exists to prevent. Header, then query, then the platform default are
 * only ever consulted for a caller that didn't pin an explicit version in
 * the URL at all (no such route exists yet — see VersionDetector's own
 * docblock).
 */
interface VersionDetectorInterface
{
    public function detect(?string $urlVersion, ?string $headerVersion, ?string $queryVersion): ApiVersion;
}

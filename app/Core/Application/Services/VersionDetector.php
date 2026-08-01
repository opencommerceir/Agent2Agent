<?php

namespace App\Core\Application\Services;

use App\Core\Domain\Services\VersionDetectorInterface;
use App\Core\Domain\ValueObjects\ApiVersion;
use Illuminate\Http\Request;

/**
 * The one VersionDetectorInterface implementation. detect() itself is the
 * pure priority chain (interface contract); detectFromRequest() is the
 * Request-touching convenience that extracts the three raw candidates
 * before handing them to it — the same "Domain interface stays pure, the
 * concrete Application class also offers a richer Request-aware entry
 * point" split TranslationServiceInterface/TranslationService established,
 * one layer over (Language -> ApiVersion).
 *
 * Only Infrastructure\Middleware\ApiVersioning calls detectFromRequest()
 * directly (type-hinting this concrete class, not the interface) — the
 * same reasoning a caller needing TranslationService's own resolve()
 * type-hints TranslationService directly instead of
 * TranslationServiceInterface.
 */
final class VersionDetector implements VersionDetectorInterface
{
    public function detect(?string $urlVersion, ?string $headerVersion, ?string $queryVersion): ApiVersion
    {
        return ApiVersion::tryFrom($urlVersion ?? '')
            ?? ApiVersion::tryFrom($headerVersion ?? '')
            ?? ApiVersion::tryFrom($queryVersion ?? '')
            ?? ApiVersion::V1;
    }

    public function detectFromRequest(Request $request): ApiVersion
    {
        return $this->detect(
            $this->extractFromPath($request->path()),
            $this->extractFromAcceptHeader($request->header('Accept')),
            $request->query('version'),
        );
    }

    /**
     * Matches the /v1/, /v2/, ... segment routes/mcp.php's own
     * Route::prefix('mcp/vN') always produces — e.g. "mcp/v2/execute" -> "v2".
     */
    private function extractFromPath(string $path): ?string
    {
        return preg_match('#(?:^|/)v(\d+)(?:/|$)#', $path, $matches) === 1
            ? 'v'.$matches[1]
            : null;
    }

    /**
     * "Accept: application/vnd.opencommerce.v2+json" -> "v2". Any other
     * Accept value (a plain "application/json", or none at all) yields
     * null, falling through to the next tier — this is content
     * negotiation for OUR own vendor media type, not a general Accept
     * header parser.
     */
    private function extractFromAcceptHeader(?string $acceptHeader): ?string
    {
        if ($acceptHeader === null) {
            return null;
        }

        return preg_match('#vnd\.opencommerce\.(v\d+)\+json#', $acceptHeader, $matches) === 1
            ? $matches[1]
            : null;
    }
}

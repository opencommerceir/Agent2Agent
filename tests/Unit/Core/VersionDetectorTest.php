<?php

namespace Tests\Unit\Core;

use App\Core\Application\Services\VersionDetector;
use App\Core\Domain\ValueObjects\ApiVersion;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * detect() itself is pure (three nullable strings in, an ApiVersion out —
 * no Illuminate\Http\Request involved), so this stays a plain PHPUnit
 * TestCase, the same "framework-free Domain-adjacent service" shape
 * TranslationServiceTest already establishes for TranslationService.
 * detectFromRequest()'s own Illuminate\Http\Request::create() calls need
 * no booted container — it's a Symfony HttpFoundation object underneath —
 * so those cases stay here too rather than moving to a Feature test.
 *
 * Priority, confirmed during Stage 7 planning (see
 * VersionDetectorInterface's own docblock): URL always wins when present,
 * even over an explicit conflicting header/query — this is the resolution
 * to what would otherwise be a self-contradicting request (an explicit
 * v1 URL silently returning v2-shaped data because of a stray header is
 * exactly the hidden-behavior failure mode this feature exists to
 * prevent). Header, then query, then the platform default only matter
 * when the URL carries no version segment of its own.
 */
class VersionDetectorTest extends TestCase
{
    public function test_detect_withUrlVersion_alwaysWinsRegardlessOfOtherSignals(): void
    {
        $detector = new VersionDetector();

        $this->assertSame(
            ApiVersion::V1,
            $detector->detect('v1', 'v2', 'v2'),
        );
    }

    public function test_detect_withNoUrlVersion_fallsBackToHeader(): void
    {
        $detector = new VersionDetector();

        $this->assertSame(
            ApiVersion::V2,
            $detector->detect(null, 'v2', 'v1'),
        );
    }

    public function test_detect_withNoUrlOrHeaderVersion_fallsBackToQuery(): void
    {
        $detector = new VersionDetector();

        $this->assertSame(
            ApiVersion::V2,
            $detector->detect(null, null, 'v2'),
        );
    }

    public function test_detect_withNoSignalsAtAll_defaultsToV1(): void
    {
        $detector = new VersionDetector();

        $this->assertSame(ApiVersion::V1, $detector->detect(null, null, null));
    }

    public function test_detect_withUnrecognizedCandidates_fallsThroughToDefault(): void
    {
        $detector = new VersionDetector();

        $this->assertSame(ApiVersion::V1, $detector->detect('v99', 'garbage', 'also-garbage'));
    }

    public function test_detectFromRequest_withExplicitV1Path_returnsV1EvenWithAV2Header(): void
    {
        $detector = new VersionDetector();
        $request = Request::create('/mcp/v1/execute', 'POST');
        $request->headers->set('Accept', 'application/vnd.opencommerce.v2+json');

        $this->assertSame(ApiVersion::V1, $detector->detectFromRequest($request));
    }

    public function test_detectFromRequest_withExplicitV2Path_returnsV2(): void
    {
        $detector = new VersionDetector();
        $request = Request::create('/mcp/v2/execute', 'POST');

        $this->assertSame(ApiVersion::V2, $detector->detectFromRequest($request));
    }

    public function test_detectFromRequest_withNoVersionedPath_honorsTheAcceptHeader(): void
    {
        $detector = new VersionDetector();
        $request = Request::create('/mcp/execute', 'POST');
        $request->headers->set('Accept', 'application/vnd.opencommerce.v2+json');

        $this->assertSame(ApiVersion::V2, $detector->detectFromRequest($request));
    }

    public function test_detectFromRequest_withNoVersionedPathOrHeader_honorsTheQueryParameter(): void
    {
        $detector = new VersionDetector();
        $request = Request::create('/mcp/execute?version=v2', 'POST');

        $this->assertSame(ApiVersion::V2, $detector->detectFromRequest($request));
    }

    public function test_detectFromRequest_withNothing_defaultsToV1(): void
    {
        $detector = new VersionDetector();
        $request = Request::create('/mcp/execute', 'POST');

        $this->assertSame(ApiVersion::V1, $detector->detectFromRequest($request));
    }
}

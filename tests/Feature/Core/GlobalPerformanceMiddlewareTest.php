<?php

namespace Tests\Feature\Core;

use App\Core\Application\Services\PerformanceMonitor;
use Tests\TestCase;

/**
 * RecordPerformanceMetrics/SetCDNHeaders (bootstrap/app.php's global
 * middleware stack, Phase 4 Stage 8, §7.20) exercised through Laravel's
 * real test client — the same "real HTTP requests, no mocked routing"
 * style every other Feature test in this codebase already uses.
 */
class GlobalPerformanceMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PerformanceMonitor::class)->reset();
    }

    public function test_anyRequest_incrementsTheRecordedRequestCount(): void
    {
        $this->get('/login');

        $this->assertSame(1, app(PerformanceMonitor::class)->getRequestCount());
    }

    public function test_mcpRequest_getsNoCacheHeaders(): void
    {
        // Symfony's own ResponseHeaderBag re-normalizes Cache-Control
        // directives (reorders them, adds `private` when `public` isn't
        // explicitly present) — assert on the individual directives this
        // middleware actually sets, not a literal exact-string match.
        $response = $this->postJson('/mcp/v1/execute', ['capability' => 'demo.tools.echo']);

        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $response->assertHeader('Pragma', 'no-cache');
    }

    public function test_staticAssetPath_getsLongLivedCacheHeaders(): void
    {
        $response = $this->get('/does-not-exist.css');

        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=31536000', $cacheControl);
        $this->assertStringContainsString('immutable', $cacheControl);
    }

    /**
     * Proves CompressResponse's own test-safety gate actually works: a
     * real HTTP request made through the test client, with a real
     * Accept-Encoding: gzip header, still comes back as plain readable
     * JSON — exactly what every other JSON-asserting Feature test in this
     * codebase already relies on implicitly.
     */
    public function test_requestWithGzipAcceptEncoding_isNotCompressedDuringTests(): void
    {
        $response = $this->withHeaders(['Accept-Encoding' => 'gzip'])->get('/login');

        $response->assertHeaderMissing('Content-Encoding');
        $this->assertStringContainsString('<html', $response->getContent());
    }
}

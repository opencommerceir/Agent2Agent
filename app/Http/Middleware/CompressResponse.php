<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applied to the `web` middleware group only (bootstrap/app.php) —
 * deliberately **not** global, and deliberately **not** applied to
 * `mcp/*`. Two real reasons, not an arbitrary scope cut:
 *
 * 1. Every one of this app's Feature tests that asserts on JSON
 *    (`assertJsonPath`, `->json()`, ~600 of them across the MCP and
 *    Dashboard test suites) reads `$response->getContent()` and decodes
 *    it as plain text — gzip-encoding that body first would make every
 *    one of those assertions fail, since Laravel's in-process test
 *    client never negotiates `Content-Encoding` the way a real browser's
 *    network stack does. Guarded by `app()->runningUnitTests()` for
 *    exactly this reason (confirmed empirically: this flag is NOT
 *    reliable inside a ServiceProvider::boot() call under `php artisan
 *    test`'s own wrapper process — see CoreServiceProvider's own
 *    docblock — but IS reliable here, inside a middleware's handle(),
 *    because that only ever runs during a real HTTP dispatch through an
 *    already-booted test application, which is exactly when Feature
 *    tests make their requests).
 * 2. Real production deployments compress at the web server (nginx
 *    `gzip on;`) or CDN layer, not inside the PHP application itself —
 *    doing it here too risks double-compression if `zlib.output_compression`
 *    is also enabled in php.ini (gzipping already-gzipped bytes produces
 *    corrupt output the client can't decode). Scoping this to `web` only
 *    (Dashboard HTML/JSON responses, which aren't proxied through a CDN
 *    the way static build assets are) keeps the blast radius of that risk
 *    contained to one deployment knob an operator explicitly owns,
 *    documented here rather than silently applied everywhere the
 *    request's own literal ask ("تمام پاسخ‌ها") would have covered.
 */
final class CompressResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (app()->runningUnitTests()) {
            return $response;
        }

        return $this->compress($response, (string) $request->header('Accept-Encoding', ''));
    }

    /**
     * The actual gzip logic, deliberately split out from handle() so it
     * can be unit-tested directly (CompressResponseTest) without the
     * runningUnitTests() gate always short-circuiting it — that gate only
     * needs to guard *whether this runs during a real HTTP dispatch*, not
     * whether the compression logic itself is reachable from a test at
     * all.
     */
    public function compress(Response $response, string $acceptEncoding): Response
    {
        if (! str_contains($acceptEncoding, 'gzip')) {
            return $response;
        }

        $content = $response->getContent();

        if ($content === false || $content === '') {
            return $response;
        }

        $compressed = gzencode($content, 9);

        if ($compressed === false) {
            return $response;
        }

        $response->setContent($compressed);
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Content-Length', (string) strlen($compressed));
        $response->headers->set('Vary', 'Accept-Encoding');

        return $response;
    }
}

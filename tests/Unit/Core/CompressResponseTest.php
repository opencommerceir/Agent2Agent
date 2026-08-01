<?php

namespace Tests\Unit\Core;

use App\Http\Middleware\CompressResponse;
use Illuminate\Http\Response;
use PHPUnit\Framework\TestCase;

/**
 * Tests compress() directly — the pure gzip logic, split out from
 * handle() specifically so it's reachable from a test at all (handle()
 * itself always no-ops during the test suite via
 * app()->runningUnitTests(), see this middleware's own docblock).
 */
class CompressResponseTest extends TestCase
{
    public function test_compress_withGzipAcceptEncoding_encodesTheBody(): void
    {
        $middleware = new CompressResponse();
        $response = new Response('{"hello":"world"}');

        $result = $middleware->compress($response, 'gzip, deflate, br');

        $this->assertSame('gzip', $result->headers->get('Content-Encoding'));
        $this->assertSame('Accept-Encoding', $result->headers->get('Vary'));
        $this->assertSame('{"hello":"world"}', gzdecode($result->getContent()));
    }

    public function test_compress_withoutGzipAcceptEncoding_leavesTheResponseUntouched(): void
    {
        $middleware = new CompressResponse();
        $response = new Response('{"hello":"world"}');

        $result = $middleware->compress($response, 'br');

        $this->assertNull($result->headers->get('Content-Encoding'));
        $this->assertSame('{"hello":"world"}', $result->getContent());
    }

    public function test_compress_withEmptyBody_leavesTheResponseUntouched(): void
    {
        $middleware = new CompressResponse();
        $response = new Response('');

        $result = $middleware->compress($response, 'gzip');

        $this->assertNull($result->headers->get('Content-Encoding'));
    }
}

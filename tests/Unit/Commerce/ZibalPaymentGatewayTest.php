<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Application\Services\ZibalConfig;
use App\Modules\Commerce\Application\Services\ZibalPaymentGateway;
use App\Modules\Commerce\Domain\Exceptions\PaymentGatewayException;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * No live Zibal credentials/network access assumed — every request is
 * intercepted by a Guzzle MockHandler (same discipline every external
 * Connector's own test in this codebase already uses). See
 * `test_defaultConstructor_resolvesBaseUrlAndPathToTheFullRealEndpoint()`
 * for why that alone isn't the whole story — the exact class of bug this
 * test guards against was found live in `OpenRouterClient` this session
 * (HANDOFF §7.35), applied here preemptively.
 */
class ZibalPaymentGatewayTest extends TestCase
{
    private function config(): ZibalConfig
    {
        return new ZibalConfig(merchant: 'zibal', baseUrl: 'https://gateway.zibal.ir', timeoutSeconds: 15);
    }

    public function test_initiate_withNonIrrCurrency_throws(): void
    {
        $gateway = new ZibalPaymentGateway($this->config(), new Client());

        $this->expectException(InvalidArgumentException::class);

        $gateway->initiate(Money::fromAmount(1000, 'USD'), 'https://app.test/callback', []);
    }

    public function test_initiate_onSuccess_returnsRedirectUrlAndTrackId(): void
    {
        $guzzle = $this->clientWithResponses([
            new Response(200, [], json_encode(['trackId' => 15966442233311, 'result' => 100, 'message' => 'success'])),
        ]);
        $gateway = new ZibalPaymentGateway($this->config(), $guzzle);

        $result = $gateway->initiate(Money::fromAmount(200000, 'IRR'), 'https://app.test/callback', ['reference' => '42']);

        $this->assertSame('15966442233311', $result->providerReference);
        $this->assertSame('https://gateway.zibal.ir/start/15966442233311', $result->redirectUrl);
    }

    public function test_initiate_onFailureResult_throws(): void
    {
        $guzzle = $this->clientWithResponses([
            new Response(200, [], json_encode(['result' => 105, 'message' => 'amount too small'])),
        ]);
        $gateway = new ZibalPaymentGateway($this->config(), $guzzle);

        $this->expectException(PaymentGatewayException::class);

        $gateway->initiate(Money::fromAmount(500, 'IRR'), 'https://app.test/callback', []);
    }

    public function test_verify_withResult100_isSuccessful(): void
    {
        $guzzle = $this->clientWithResponses([
            new Response(200, [], json_encode(['result' => 100, 'refNumber' => 'REF1', 'message' => 'success'])),
        ]);
        $gateway = new ZibalPaymentGateway($this->config(), $guzzle);

        $result = $gateway->verify('trk_1');

        $this->assertTrue($result->successful);
        $this->assertSame('REF1', $result->transactionId);
    }

    public function test_verify_withResult201_alreadyConfirmed_isStillSuccessful(): void
    {
        $guzzle = $this->clientWithResponses([
            new Response(200, [], json_encode(['result' => 201, 'message' => 'already confirmed'])),
        ]);
        $gateway = new ZibalPaymentGateway($this->config(), $guzzle);

        $result = $gateway->verify('trk_1');

        $this->assertTrue($result->successful);
    }

    public function test_verify_withOtherResult_isNotSuccessful(): void
    {
        $guzzle = $this->clientWithResponses([
            new Response(200, [], json_encode(['result' => 202, 'message' => 'not paid'])),
        ]);
        $gateway = new ZibalPaymentGateway($this->config(), $guzzle);

        $result = $gateway->verify('trk_1');

        $this->assertFalse($result->successful);
        $this->assertNull($result->transactionId);
    }

    public function test_inquiry_withPaidConfirmedStatus_isSuccessful(): void
    {
        $guzzle = $this->clientWithResponses([
            new Response(200, [], json_encode(['result' => 100, 'status' => 1, 'refNumber' => 'REF2'])),
        ]);
        $gateway = new ZibalPaymentGateway($this->config(), $guzzle);

        $result = $gateway->inquiry('trk_1');

        $this->assertTrue($result->successful);
        $this->assertSame('REF2', $result->transactionId);
    }

    public function test_inquiry_withPendingStatus_isNotSuccessful(): void
    {
        $guzzle = $this->clientWithResponses([
            new Response(200, [], json_encode(['result' => 100, 'status' => -1])),
        ]);
        $gateway = new ZibalPaymentGateway($this->config(), $guzzle);

        $result = $gateway->inquiry('trk_1');

        $this->assertFalse($result->successful);
    }

    public function test_request_sendsCorrectPath(): void
    {
        $container = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode(['result' => 100, 'trackId' => 1])),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(Middleware::history($container));
        $guzzle = new Client(['handler' => $handlerStack]);

        $gateway = new ZibalPaymentGateway($this->config(), $guzzle);
        $gateway->initiate(Money::fromAmount(200000, 'IRR'), 'https://app.test/callback', []);

        // No leading slash — the same real-request-URI convention
        // `OpenRouterClient` needed fixing to (§7.35), applied here from
        // the start.
        $this->assertSame('v1/request', $container[0]['request']->getUri()->getPath());
    }

    /**
     * Every other test above injects `$http` directly, bypassing the
     * constructor's own `$http ??= new Client(['base_uri' => ...])`
     * branch — this test reaches that exact branch via reflection (no
     * network access) and resolves the real request URI the same way
     * Guzzle does internally, so the OpenRouterClient-class bug (base_uri
     * silently dropping a path segment) can't come back unnoticed here.
     */
    public function test_defaultConstructor_resolvesBaseUrlAndPathToTheFullRealEndpoint(): void
    {
        $gateway = new ZibalPaymentGateway($this->config());

        $property = new \ReflectionProperty($gateway, 'http');
        $property->setAccessible(true);
        $guzzle = $property->getValue($gateway);

        $resolved = \GuzzleHttp\Psr7\UriResolver::resolve(
            \GuzzleHttp\Psr7\Utils::uriFor($guzzle->getConfig('base_uri')),
            \GuzzleHttp\Psr7\Utils::uriFor('v1/request'),
        );

        $this->assertSame('https://gateway.zibal.ir/v1/request', (string) $resolved);
    }

    /**
     * @param list<Response> $responses
     */
    private function clientWithResponses(array $responses): Client
    {
        return new Client(['handler' => HandlerStack::create(new MockHandler($responses))]);
    }
}

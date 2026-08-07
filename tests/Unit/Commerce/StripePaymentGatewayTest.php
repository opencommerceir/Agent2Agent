<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Application\Services\StripeConfig;
use App\Modules\Commerce\Application\Services\StripePaymentGateway;
use App\Modules\Commerce\Domain\Exceptions\PaymentGatewayException;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * No live Stripe credentials/network access assumed — every request is
 * intercepted by a Guzzle MockHandler. Request shape (form-encoded,
 * nested `line_items[0][price_data][...]` bracket notation, HTTP Basic
 * auth) verified live against docs.stripe.com this session (§7.37), not
 * from memory — see `StripePaymentGateway`'s own docblock.
 */
class StripePaymentGatewayTest extends TestCase
{
    private function config(): StripeConfig
    {
        return new StripeConfig(
            secretKey: 'sk_test_123',
            webhookSecret: 'whsec_test',
            baseUrl: 'https://api.stripe.com',
            timeoutSeconds: 15,
        );
    }

    public function test_initiate_onSuccess_returnsRedirectUrlAndSessionId(): void
    {
        $guzzle = $this->clientWithResponses([
            new Response(200, [], json_encode([
                'id' => 'cs_test_123',
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_123',
                'payment_status' => 'unpaid',
            ])),
        ]);
        $gateway = new StripePaymentGateway($this->config(), $guzzle);

        $result = $gateway->initiate(Money::fromAmount(2000, 'USD'), 'https://app.test/callback?session=42', ['reference' => '42']);

        $this->assertSame('cs_test_123', $result->providerReference);
        $this->assertSame('https://checkout.stripe.com/c/pay/cs_test_123', $result->redirectUrl);
    }

    public function test_initiate_sendsFormEncodedNestedLineItemsAndBasicAuth(): void
    {
        $container = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode(['id' => 'cs_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_1'])),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(Middleware::history($container));
        $guzzle = new Client(['handler' => $handlerStack, 'auth' => ['sk_test_123', '']]);

        $gateway = new StripePaymentGateway($this->config(), $guzzle);
        $gateway->initiate(Money::fromAmount(2000, 'USD'), 'https://app.test/callback?session=42', ['reference' => '42', 'description' => 'Order #42']);

        $sentRequest = $container[0]['request'];
        $this->assertSame('v1/checkout/sessions', $sentRequest->getUri()->getPath());
        $this->assertStringStartsWith('Basic ', $sentRequest->getHeaderLine('Authorization'));

        parse_str((string) $sentRequest->getBody(), $body);
        $this->assertSame('payment', $body['mode']);
        $this->assertSame('usd', $body['line_items'][0]['price_data']['currency']);
        $this->assertSame('2000', $body['line_items'][0]['price_data']['unit_amount']);
        $this->assertSame('Order #42', $body['line_items'][0]['price_data']['product_data']['name']);
        // Both properly joined with `&`, never a malformed double-`?` —
        // the callback URL already carries its own `?session=42` query
        // string (InitiatePaymentAction's own convention).
        $this->assertSame('https://app.test/callback?session=42&checkout=success', $body['success_url']);
        $this->assertSame('https://app.test/callback?session=42&checkout=cancelled', $body['cancel_url']);
    }

    public function test_verify_withPaidStatus_isSuccessful(): void
    {
        $guzzle = $this->clientWithResponses([
            new Response(200, [], json_encode(['payment_status' => 'paid', 'payment_intent' => 'pi_123'])),
        ]);
        $gateway = new StripePaymentGateway($this->config(), $guzzle);

        $result = $gateway->verify('cs_test_123');

        $this->assertTrue($result->successful);
        $this->assertSame('pi_123', $result->transactionId);
    }

    public function test_verify_withUnpaidStatus_isNotSuccessful(): void
    {
        $guzzle = $this->clientWithResponses([
            new Response(200, [], json_encode(['payment_status' => 'unpaid'])),
        ]);
        $gateway = new StripePaymentGateway($this->config(), $guzzle);

        $result = $gateway->verify('cs_test_123');

        $this->assertFalse($result->successful);
        $this->assertNull($result->transactionId);
    }

    public function test_inquiry_usesGetOnTheSessionResource(): void
    {
        $container = [];
        $mock = new MockHandler([new Response(200, [], json_encode(['payment_status' => 'paid']))]);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(Middleware::history($container));
        $guzzle = new Client(['handler' => $handlerStack]);

        $gateway = new StripePaymentGateway($this->config(), $guzzle);
        $gateway->inquiry('cs_test_123');

        $sentRequest = $container[0]['request'];
        $this->assertSame('GET', $sentRequest->getMethod());
        $this->assertSame('v1/checkout/sessions/cs_test_123', $sentRequest->getUri()->getPath());
    }

    public function test_request_throwsOnHttpFailure(): void
    {
        $mock = new MockHandler([
            new ClientException(
                'error',
                new Request('POST', 'v1/checkout/sessions'),
                new Response(402, [], json_encode(['error' => ['message' => 'card declined']])),
            ),
        ]);
        $guzzle = new Client(['handler' => HandlerStack::create($mock)]);
        $gateway = new StripePaymentGateway($this->config(), $guzzle);

        $this->expectException(PaymentGatewayException::class);

        $gateway->initiate(Money::fromAmount(2000, 'USD'), 'https://app.test/callback', []);
    }

    /**
     * Same reflection-based real-`base_uri` regression test
     * `ZibalPaymentGatewayTest`/`OpenRouterClientTest` both already
     * establish this session (§7.35/§7.37) — every other test above
     * injects `$http` directly, bypassing the constructor's own
     * `$http ??= new Client([...])` branch entirely.
     */
    public function test_defaultConstructor_resolvesBaseUrlAndPathToTheFullRealEndpoint(): void
    {
        $gateway = new StripePaymentGateway($this->config());

        $property = new \ReflectionProperty($gateway, 'http');
        $property->setAccessible(true);
        $guzzle = $property->getValue($gateway);

        $resolved = \GuzzleHttp\Psr7\UriResolver::resolve(
            \GuzzleHttp\Psr7\Utils::uriFor($guzzle->getConfig('base_uri')),
            \GuzzleHttp\Psr7\Utils::uriFor('v1/checkout/sessions'),
        );

        $this->assertSame('https://api.stripe.com/v1/checkout/sessions', (string) $resolved);
    }

    /**
     * @param list<Response> $responses
     */
    private function clientWithResponses(array $responses): Client
    {
        return new Client(['handler' => HandlerStack::create(new MockHandler($responses))]);
    }
}

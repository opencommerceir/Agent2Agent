<?php

namespace App\Modules\Commerce\Application\Services;

use App\Modules\Commerce\Domain\Exceptions\PaymentGatewayException;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;

/**
 * The real `RedirectPaymentGatewayInterface` implementation for Stripe,
 * via Checkout Sessions — the hosted-page flow that mirrors Zibal's own
 * request/redirect/callback/verify shape as closely as Stripe's API
 * allows (verified live against docs.stripe.com this session, §7.37 —
 * not from memory): create a Session -> redirect the buyer to its own
 * `url` -> a webhook (`checkout.session.completed`,
 * `StripeWebhookVerifier`) or the `success_url` redirect tells us to
 * check again -> retrieve the Session server-side, `payment_status ===
 * 'paid'` is the only thing ever trusted.
 *
 * **Stripe's entire API is `application/x-www-form-urlencoded`, not
 * JSON** (Guzzle's own `form_params` + PHP's `http_build_query` produce
 * the required `line_items[0][price_data][currency]=...` bracket
 * notation automatically for a nested PHP array — no manual string
 * building needed) — the one genuine request-shape difference from
 * every other real HTTP Connector in this codebase, all of which are
 * JSON. Auth is HTTP Basic with the secret key as the username, no
 * password (Guzzle's own `auth` request option).
 *
 * Same `base_uri` convention as `ZibalPaymentGateway` (trailing slash,
 * no leading slash on request paths) — the exact bug already found and
 * fixed in `OpenRouterClient` this session (§7.35), applied here
 * preemptively rather than rediscovered.
 */
final class StripePaymentGateway implements RedirectPaymentGatewayInterface
{
    private const SESSIONS_PATH = 'v1/checkout/sessions';

    private readonly ClientInterface $http;

    public function __construct(
        private readonly StripeConfig $config,
        ?ClientInterface $http = null,
    ) {
        $this->http = $http ?? new Client([
            'base_uri' => $this->config->baseUrl.'/',
            'timeout' => $this->config->timeoutSeconds,
            'auth' => [$this->config->secretKey, ''],
        ]);
    }

    public function getName(): string
    {
        return 'stripe';
    }

    public function initiate(Money $amount, string $callbackUrl, array $metadata): PaymentInitiationResult
    {
        $reference = $metadata['reference'] ?? null;

        $body = $this->request('POST', self::SESSIONS_PATH, [
            'mode' => 'payment',
            'success_url' => $this->appendQuery($callbackUrl, 'checkout=success'),
            'cancel_url' => $this->appendQuery($callbackUrl, 'checkout=cancelled'),
            'client_reference_id' => $reference,
            'metadata' => array_filter([
                'reference' => $reference,
                'description' => $metadata['description'] ?? null,
            ], static fn ($value) => $value !== null),
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($amount->currency()),
                    'unit_amount' => $amount->amount(),
                    'product_data' => [
                        'name' => $metadata['description'] ?? 'Order payment',
                    ],
                ],
            ]],
        ]);

        return new PaymentInitiationResult(
            redirectUrl: $body['url'],
            providerReference: $body['id'],
            rawResponse: $body,
        );
    }

    public function verify(string $providerReference): PaymentGatewayResult
    {
        return $this->resolve($providerReference);
    }

    public function inquiry(string $providerReference): PaymentGatewayResult
    {
        return $this->resolve($providerReference);
    }

    /**
     * `$callbackUrl` may already carry a query string (this codebase's
     * own `InitiatePaymentAction` always includes `?session=...`) —
     * naive string concatenation would produce an invalid double-`?`
     * URL. Correctly uses `&` when one is already present.
     */
    private function appendQuery(string $url, string $query): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?').$query;
    }

    private function resolve(string $providerReference): PaymentGatewayResult
    {
        $body = $this->request('GET', self::SESSIONS_PATH.'/'.rawurlencode($providerReference), []);

        $successful = ($body['payment_status'] ?? null) === 'paid';

        return new PaymentGatewayResult(
            successful: $successful,
            transactionId: $successful ? ($body['payment_intent'] ?? $providerReference) : null,
            rawResponse: $body,
        );
    }

    /**
     * @param array<string, mixed> $formParams
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $formParams): array
    {
        try {
            $options = $method === 'GET' ? [] : ['form_params' => $formParams];
            $response = $this->http->request($method, $path, $options);
        } catch (ClientException $e) {
            throw new PaymentGatewayException(
                'Stripe API request failed: '.$e->getMessage(),
                previous: $e,
            );
        } catch (GuzzleException $e) {
            throw new PaymentGatewayException("Stripe API request failed: {$e->getMessage()}", previous: $e);
        }

        $decoded = json_decode((string) $response->getBody(), true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            throw new PaymentGatewayException('Stripe API returned a malformed (non-JSON-object) response.');
        }

        return $decoded;
    }
}

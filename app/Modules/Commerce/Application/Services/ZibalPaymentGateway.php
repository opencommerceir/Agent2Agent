<?php

namespace App\Modules\Commerce\Application\Services;

use App\Modules\Commerce\Domain\Exceptions\PaymentGatewayException;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;

/**
 * The real `RedirectPaymentGatewayInterface` implementation for Zibal
 * (Iranian IPG) — request -> redirect -> callback -> verify, per Zibal's
 * own docs (§7.37). Zibal is Rial-only; `initiate()` throws a plain
 * `InvalidArgumentException` (the same "caller input problem" shape
 * `CalculatePricingAction`'s own "Cart is empty" check already uses,
 * mapped to 422 by `MCPExceptionHandler`) if given anything but IRR —
 * never silently mis-charges in the wrong currency.
 *
 * **Guzzle `base_uri` convention, applied deliberately from the start**
 * (the exact bug this session already found and fixed in
 * `OpenRouterClient`, HANDOFF §7.35/§7.37): `base_uri` always ends with
 * `/`, every request path is relative with **no** leading `/`, so RFC
 * 3986's merge rule appends rather than replaces. Zibal's own `/start/{trackId}`
 * redirect page lives under a *different* root than `/v1/*` — deliberately
 * never built through this class's own Guzzle client at all (no request is
 * ever made to it from here), just plain string concatenation against the
 * config's own `baseUrl`, so the two path families can never be conflated.
 *
 * **Known, documented gap**: `verify()`/`inquiry()`'s exact response body
 * field names (`amount`/`status`/`cardNumber`/`paidAt`/`refNumber`) are
 * implemented from Zibal's well-known public API shape, not the docs
 * pasted this session (those two sections were collapsed) — confirmed
 * as an acceptable, flagged gap with the user before writing this class;
 * the numeric **result codes** below (100/102/103/104/105/106/201/202/203)
 * and the transaction **status codes** (-1/-2/1/2/3/...) are both taken
 * verbatim from the tables the user did paste in full.
 */
final class ZibalPaymentGateway implements RedirectPaymentGatewayInterface
{
    private const REQUEST_PATH = 'v1/request';

    private const VERIFY_PATH = 'v1/verify';

    private const INQUIRY_PATH = 'v1/inquiry';

    /** Zibal's own "already confirmed" result — not a failure, an idempotent success. */
    private const RESULT_ALREADY_CONFIRMED = 201;

    private const RESULT_SUCCESS = 100;

    /** "پرداخت شده - تاییدشده" — paid and confirmed, per Zibal's own status table. */
    private const STATUS_PAID_CONFIRMED = 1;

    private readonly ClientInterface $http;

    public function __construct(
        private readonly ZibalConfig $config,
        ?ClientInterface $http = null,
    ) {
        $this->http = $http ?? new Client([
            'base_uri' => $this->config->baseUrl.'/',
            'timeout' => $this->config->timeoutSeconds,
        ]);
    }

    public function getName(): string
    {
        return 'zibal';
    }

    public function initiate(Money $amount, string $callbackUrl, array $metadata): PaymentInitiationResult
    {
        if ($amount->currency() !== 'IRR') {
            throw new InvalidArgumentException(
                "Zibal only accepts IRR, got [{$amount->currency()}]. Route this charge to a different gateway."
            );
        }

        $body = $this->post(self::REQUEST_PATH, array_filter([
            'merchant' => $this->config->merchant,
            'amount' => $amount->amount(),
            'callbackUrl' => $callbackUrl,
            'orderId' => $metadata['reference'] ?? null,
            'description' => $metadata['description'] ?? null,
            'mobile' => $metadata['mobile'] ?? null,
        ], static fn ($value) => $value !== null));

        if (($body['result'] ?? null) !== self::RESULT_SUCCESS) {
            throw new PaymentGatewayException(
                'Zibal payment request failed: '.($body['message'] ?? 'no message given by the gateway.')
            );
        }

        $trackId = (string) $body['trackId'];

        return new PaymentInitiationResult(
            redirectUrl: "{$this->config->baseUrl}/start/{$trackId}",
            providerReference: $trackId,
            rawResponse: $body,
        );
    }

    public function verify(string $providerReference): PaymentGatewayResult
    {
        $body = $this->post(self::VERIFY_PATH, [
            'merchant' => $this->config->merchant,
            'trackId' => $providerReference,
        ]);

        $result = $body['result'] ?? null;
        $successful = $result === self::RESULT_SUCCESS || $result === self::RESULT_ALREADY_CONFIRMED;

        return new PaymentGatewayResult(
            successful: $successful,
            transactionId: $successful ? ($body['refNumber'] ?? $providerReference) : null,
            rawResponse: $body,
        );
    }

    public function inquiry(string $providerReference): PaymentGatewayResult
    {
        $body = $this->post(self::INQUIRY_PATH, [
            'merchant' => $this->config->merchant,
            'trackId' => $providerReference,
        ]);

        $successful = ($body['result'] ?? null) === self::RESULT_SUCCESS
            && ($body['status'] ?? null) === self::STATUS_PAID_CONFIRMED;

        return new PaymentGatewayResult(
            successful: $successful,
            transactionId: $successful ? ($body['refNumber'] ?? $providerReference) : null,
            rawResponse: $body,
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        try {
            $response = $this->http->request('POST', $path, ['json' => $payload]);
        } catch (GuzzleException $e) {
            throw new PaymentGatewayException("Zibal API request failed: {$e->getMessage()}", previous: $e);
        }

        $decoded = json_decode((string) $response->getBody(), true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            throw new PaymentGatewayException('Zibal API returned a malformed (non-JSON-object) response.');
        }

        return $decoded;
    }
}

<?php

namespace App\Modules\Commerce\Application\Services;

use App\Modules\Commerce\Domain\ValueObjects\Money;

/**
 * The default `RedirectPaymentGatewayInterface` implementation
 * (`PAYMENT_GATEWAY` defaults to `mock`, same "safe default, explicit
 * opt-in for real infra" reasoning `PLANNER_TYPE=deterministic` already
 * establishes, §7.28) — no real HTTP call, deterministic, registered as
 * `'mock'`. Mirrors `MockPaymentGateway`'s own "always succeeds unless
 * told to simulate failure" convention: pass
 * `$metadata['simulate_failure'] = true` to `initiate()` to make the
 * *later* `verify()`/`inquiry()` call for that same session report a
 * decline — encoded into the fake providerReference itself, since
 * verify()/inquiry() only ever receive that one string, the same
 * constraint a real gateway's own API has (§7.37).
 */
final class MockRedirectPaymentGateway implements RedirectPaymentGatewayInterface
{
    private const DECLINE_PREFIX = 'mock_declined_';

    private const SUCCESS_PREFIX = 'mock_ref_';

    public function getName(): string
    {
        return 'mock';
    }

    public function initiate(Money $amount, string $callbackUrl, array $metadata): PaymentInitiationResult
    {
        $prefix = ($metadata['simulate_failure'] ?? false) ? self::DECLINE_PREFIX : self::SUCCESS_PREFIX;
        $reference = $prefix.bin2hex(random_bytes(8));

        return new PaymentInitiationResult(
            redirectUrl: "https://mock-gateway.test/pay/{$reference}?callback=".urlencode($callbackUrl),
            providerReference: $reference,
            rawResponse: ['gateway' => 'mock', 'amount' => $amount->amount(), 'currency' => $amount->currency()],
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

    private function resolve(string $providerReference): PaymentGatewayResult
    {
        if (str_starts_with($providerReference, self::DECLINE_PREFIX)) {
            return new PaymentGatewayResult(
                successful: false,
                transactionId: null,
                rawResponse: ['gateway' => 'mock', 'message' => 'The mock gateway was told to simulate a decline.'],
            );
        }

        return new PaymentGatewayResult(
            successful: true,
            transactionId: $providerReference,
            rawResponse: ['gateway' => 'mock', 'status' => 'succeeded'],
        );
    }
}

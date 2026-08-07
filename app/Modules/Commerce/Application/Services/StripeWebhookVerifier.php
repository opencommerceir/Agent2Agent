<?php

namespace App\Modules\Commerce\Application\Services;

/**
 * Manual `Stripe-Signature` header verification — no `stripe-php` SDK
 * dependency added (matches this codebase's own "standard library /
 * Guzzle only" convention every external Connector already follows,
 * §7.34's own SDK work carries the identical reasoning one level over).
 * Verified live against docs.stripe.com this session (§7.37), not from
 * memory: pure, framework-free (same shape `TaxCalculationService`/
 * `PricingService` already establish) — no Repository, no config()
 * call, everything it needs is passed in.
 *
 * Header shape: `t=<unix-timestamp>,v1=<hex-hmac>[,v1=<hex-hmac>...][,v0=...]`.
 * `v0` is a deliberate downgrade-attack decoy Stripe sends alongside real
 * test events — always ignored. Multiple `v1` entries can appear during
 * a secret rotation window (both the old and new secret's own signature,
 * per Stripe's own docs) — this class accepts a match against **any** of
 * them, not just the first.
 */
final class StripeWebhookVerifier
{
    private const DEFAULT_TOLERANCE_SECONDS = 300;

    /**
     * $rawPayload MUST be the exact, unparsed request body — any
     * re-serialization (even semantically identical JSON) changes the
     * bytes being signed and makes every real webhook fail verification.
     */
    public function verify(
        string $rawPayload,
        string $signatureHeader,
        string $webhookSecret,
        int $toleranceSeconds = self::DEFAULT_TOLERANCE_SECONDS,
    ): bool {
        [$timestamp, $signatures] = $this->parseHeader($signatureHeader);

        if ($timestamp === null || $signatures === []) {
            return false;
        }

        if (abs(time() - $timestamp) > $toleranceSeconds) {
            return false;
        }

        $signedPayload = "{$timestamp}.{$rawPayload}";
        $expected = hash_hmac('sha256', $signedPayload, $webhookSecret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0: ?int, 1: list<string>}
     */
    private function parseHeader(string $header): array
    {
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $element) {
            $parts = explode('=', trim($element), 2);

            if (count($parts) !== 2) {
                continue;
            }

            [$prefix, $value] = $parts;

            if ($prefix === 't') {
                $timestamp = ctype_digit($value) ? (int) $value : null;
            } elseif ($prefix === 'v1') {
                $signatures[] = $value;
            }
        }

        return [$timestamp, $signatures];
    }
}

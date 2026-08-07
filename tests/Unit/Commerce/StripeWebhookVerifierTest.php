<?php

namespace Tests\Unit\Commerce;

use App\Modules\Commerce\Application\Services\StripeWebhookVerifier;
use PHPUnit\Framework\TestCase;

/**
 * Verified against docs.stripe.com's own manual-verification algorithm
 * this session (§7.37), not from memory. Every signature here is
 * self-generated with the identical `hash_hmac('sha256', "{t}.{payload}", $secret)`
 * formula the verifier itself uses — the same "no stripe-php SDK, no
 * live webhook needed to test honestly" shape every other Guzzle-backed
 * Connector's own test in this codebase already establishes.
 */
class StripeWebhookVerifierTest extends TestCase
{
    private const SECRET = 'whsec_test_secret';

    private function sign(string $payload, int $timestamp, string $secret = self::SECRET): string
    {
        return hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);
    }

    public function test_verify_withValidSignature_returnsTrue(): void
    {
        $verifier = new StripeWebhookVerifier();
        $payload = '{"type":"checkout.session.completed"}';
        $timestamp = time();
        $header = "t={$timestamp},v1=".$this->sign($payload, $timestamp);

        $this->assertTrue($verifier->verify($payload, $header, self::SECRET));
    }

    public function test_verify_withWrongSecret_returnsFalse(): void
    {
        $verifier = new StripeWebhookVerifier();
        $payload = '{"type":"checkout.session.completed"}';
        $timestamp = time();
        $header = "t={$timestamp},v1=".$this->sign($payload, $timestamp, 'a_different_secret');

        $this->assertFalse($verifier->verify($payload, $header, self::SECRET));
    }

    public function test_verify_withTamperedPayload_returnsFalse(): void
    {
        $verifier = new StripeWebhookVerifier();
        $timestamp = time();
        $header = "t={$timestamp},v1=".$this->sign('{"type":"original"}', $timestamp);

        $this->assertFalse($verifier->verify('{"type":"tampered"}', $header, self::SECRET));
    }

    public function test_verify_withExpiredTimestamp_returnsFalse(): void
    {
        $verifier = new StripeWebhookVerifier();
        $payload = '{"type":"checkout.session.completed"}';
        $oldTimestamp = time() - 600; // 10 minutes ago, beyond the 300s default tolerance
        $header = "t={$oldTimestamp},v1=".$this->sign($payload, $oldTimestamp);

        $this->assertFalse($verifier->verify($payload, $header, self::SECRET));
    }

    public function test_verify_withCustomTolerance_acceptsAnOlderTimestamp(): void
    {
        $verifier = new StripeWebhookVerifier();
        $payload = '{"type":"checkout.session.completed"}';
        $oldTimestamp = time() - 600;
        $header = "t={$oldTimestamp},v1=".$this->sign($payload, $oldTimestamp);

        $this->assertTrue($verifier->verify($payload, $header, self::SECRET, toleranceSeconds: 3600));
    }

    public function test_verify_withMultipleV1Signatures_acceptsAnyMatch(): void
    {
        $verifier = new StripeWebhookVerifier();
        $payload = '{"type":"checkout.session.completed"}';
        $timestamp = time();
        $realSignature = $this->sign($payload, $timestamp);
        $header = "t={$timestamp},v1=deadbeef,v1={$realSignature}";

        $this->assertTrue($verifier->verify($payload, $header, self::SECRET));
    }

    public function test_verify_ignoresTheV0DowngradeDecoySignature(): void
    {
        $verifier = new StripeWebhookVerifier();
        $payload = '{"type":"checkout.session.completed"}';
        $timestamp = time();
        // v0 alone (no real v1) must never verify, even if it happens to
        // "match" something — Stripe's own docs: only v1 is trusted.
        $header = "t={$timestamp},v0=".$this->sign($payload, $timestamp);

        $this->assertFalse($verifier->verify($payload, $header, self::SECRET));
    }

    public function test_verify_withMalformedHeader_returnsFalse(): void
    {
        $verifier = new StripeWebhookVerifier();

        $this->assertFalse($verifier->verify('{}', 'not-a-valid-header', self::SECRET));
    }

    public function test_verify_withEmptyHeader_returnsFalse(): void
    {
        $verifier = new StripeWebhookVerifier();

        $this->assertFalse($verifier->verify('{}', '', self::SECRET));
    }
}

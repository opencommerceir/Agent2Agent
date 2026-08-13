<?php

namespace Tests\Unit\Nexus\Business;

use App\Domains\Nexus\Business\Domain\Services\TotpService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * RFC 6238 Appendix B's own published SHA1 test vectors — the ASCII string
 * "12345678901234567890" is the RAW HMAC key (not base32) per the RFC, and
 * the table's "TOTP" column is an 8-digit truncated value; this codebase
 * uses 6 digits, which is exactly that value mod 10^6 (e.g. 94287082 ->
 * 287082) — the same truncated integer, just fewer printed digits, not a
 * different algorithm.
 */
class TotpServiceTest extends TestCase
{
    private const RFC_SECRET = '12345678901234567890';

    /**
     * @return array<string, array{0: int, 1: string}>
     */
    public static function rfcVectors(): array
    {
        return [
            'T=59' => [59, '287082'],
            'T=1111111109' => [1111111109, '081804'],
            'T=1111111111' => [1111111111, '050471'],
            'T=1234567890' => [1234567890, '005924'],
            'T=2000000000' => [2000000000, '279037'],
        ];
    }

    #[DataProvider('rfcVectors')]
    public function test_code_matchesRfc6238PublishedVectors(int $time, string $expectedCode): void
    {
        $service = new TotpService();
        $counter = intdiv($time, 30);

        $this->assertSame($expectedCode, $service->code(self::RFC_SECRET, $counter));
    }

    public function test_verifyAt_acceptsTheExactTimeStep(): void
    {
        $service = new TotpService();
        $secret = $this->base32OfRfcSecret($service);

        $this->assertTrue($service->verifyAt($secret, '287082', 59));
    }

    public function test_verifyAt_toleratesOneStepOfClockDrift(): void
    {
        $service = new TotpService();
        $secret = $this->base32OfRfcSecret($service);

        // code for T=59 (step 1); asking at T=89 (step 2, one step later)
        // must still accept it within the +-1 window.
        $this->assertTrue($service->verifyAt($secret, '287082', 89, window: 1));
    }

    public function test_verifyAt_rejectsBeyondTheWindow(): void
    {
        $service = new TotpService();
        $secret = $this->base32OfRfcSecret($service);

        // step 1 vs step 3 (two steps away) — outside a +-1 window.
        $this->assertFalse($service->verifyAt($secret, '287082', 119, window: 1));
    }

    public function test_verifyAt_rejectsWrongCode(): void
    {
        $service = new TotpService();
        $secret = $this->base32OfRfcSecret($service);

        $this->assertFalse($service->verifyAt($secret, '000000', 59));
    }

    public function test_verifyAt_rejectsNonNumericOrWrongLength(): void
    {
        $service = new TotpService();
        $secret = $this->base32OfRfcSecret($service);

        $this->assertFalse($service->verifyAt($secret, 'abcdef', 59));
        $this->assertFalse($service->verifyAt($secret, '12345', 59));
    }

    public function test_generateSecret_roundTripsThroughVerify(): void
    {
        $service = new TotpService();
        $secret = $service->generateSecret();
        $now = time();
        $counter = intdiv($now, 30);

        // Derive the real code the same way verify() would, to prove
        // generateSecret()'s own base32 output actually decodes back to
        // usable key material (not just structurally valid-looking output).
        $code = $this->codeForBase32Secret($service, $secret, $counter);

        $this->assertTrue($service->verifyAt($secret, $code, $now));
    }

    private function base32OfRfcSecret(TotpService $service): string
    {
        // generateSecret()'s own base32 encoder, exercised indirectly:
        // encode the RFC's raw key the same way, via reflection into the
        // private encoder, would be the "pure" approach — but since
        // verifyAt() only accepts base32 input, round-tripping through the
        // public API (encode via a fresh dummy secret is not equivalent).
        // Simplest correct approach: reuse the private base32 encoder via
        // Reflection so the RFC vector can be driven through the real
        // public verifyAt() surface.
        $reflection = new \ReflectionMethod(TotpService::class, 'base32Encode');
        $reflection->setAccessible(true);

        return $reflection->invoke($service, self::RFC_SECRET);
    }

    private function codeForBase32Secret(TotpService $service, string $base32Secret, int $counter): string
    {
        $decode = new \ReflectionMethod(TotpService::class, 'base32Decode');
        $decode->setAccessible(true);
        $rawKey = $decode->invoke($service, $base32Secret);

        return $service->code($rawKey, $counter);
    }
}

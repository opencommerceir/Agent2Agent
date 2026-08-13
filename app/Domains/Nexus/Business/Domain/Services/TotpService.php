<?php

namespace App\Domains\Nexus\Business\Domain\Services;

/**
 * Hand-rolled RFC 6238 TOTP (HMAC-SHA1, 6 digits, 30s step) — a deliberate,
 * flagged judgment call: the more common real-world choice for this is a
 * package (e.g. pragmarx/google2fa), but the algorithm itself is small and
 * fully specified, and this codebase's entire runtime dependency list is 5
 * packages before Phase 7 (laravel/framework, laravel/tinker,
 * barryvdh/laravel-dompdf, opencommerce/sdk, predis/predis) — overridable if
 * a real vulnerability/maintenance concern ever makes the package the
 * better call. Framework-free (Domain Layer Rules) — plain PHP hashing
 * primitives only.
 *
 * No QR code image generation — the setup view shows the raw secret and
 * otpauth:// URI as text for manual entry, the same "no new dependency for
 * something skippable" call Network Visualization (Phase 5/M4) made for
 * inline-SVG over a charting library.
 */
final class TotpService
{
    private const DIGITS = 6;
    private const PERIOD_SECONDS = 30;
    private const SECRET_BYTES = 20; // 160-bit, standard TOTP key size
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(self::SECRET_BYTES));
    }

    public function otpauthUri(string $base32Secret, string $accountLabel, string $issuer = 'Nexus'): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            rawurlencode($issuer),
            rawurlencode($accountLabel),
            $base32Secret,
            rawurlencode($issuer),
            self::DIGITS,
            self::PERIOD_SECONDS,
        );
    }

    /**
     * ±1 time-step window (RFC 6238 §5.2's own recommendation) — tolerates
     * ordinary clock drift between the server and the authenticator app
     * without meaningfully weakening the 30-second code lifetime.
     */
    public function verify(string $base32Secret, string $code, int $window = 1): bool
    {
        return $this->verifyAt($base32Secret, $code, time(), $window);
    }

    public function verifyAt(string $base32Secret, string $code, int $timestamp, int $window = 1): bool
    {
        $code = trim($code);

        if (! ctype_digit($code) || strlen($code) !== self::DIGITS) {
            return false;
        }

        $currentStep = intdiv($timestamp, self::PERIOD_SECONDS);
        $key = $this->base32Decode($base32Secret);

        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals($this->code($key, $currentStep + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The raw RFC 4226 HOTP algorithm (RFC 6238 is just HOTP with a
     * time-derived counter) — takes the already-decoded key so it can be
     * tested directly against RFC 6238 Appendix B's published test vectors
     * (which use a raw ASCII-bytes key, not a base32 secret) without this
     * class's own base32 layer being part of what's under test.
     */
    public function code(string $rawKey, int $counter): string
    {
        $counterBytes = pack('N*', 0, $counter); // 8-byte big-endian counter
        $hash = hash_hmac('sha1', $counterBytes, $rawKey, true);
        $offset = ord($hash[19]) & 0x0F;

        $truncated = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($truncated % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $data): string
    {
        $bits = '';
        foreach (str_split($data) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $output = '';
        foreach (str_split($bits, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $output .= self::BASE32_ALPHABET[bindec($chunk)];
        }

        return $output;
    }

    private function base32Decode(string $base32): string
    {
        $base32 = strtoupper(rtrim($base32, '='));

        $bits = '';
        foreach (str_split($base32) as $char) {
            $position = strpos(self::BASE32_ALPHABET, $char);

            if ($position === false) {
                continue;
            }

            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        foreach (str_split($bits, 8) as $byteBits) {
            if (strlen($byteBits) < 8) {
                break; // drop incomplete trailing bits, same as any base32 decoder
            }

            $bytes .= chr(bindec($byteBits));
        }

        return $bytes;
    }
}

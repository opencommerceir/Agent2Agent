<?php

namespace App\Core\Application\Services;

use App\Core\Domain\Services\DeprecationNotifierInterface;
use App\Core\Domain\ValueObjects\ApiVersion;
use App\Core\Domain\ValueObjects\SunsetDate;

/**
 * Reads config('api.deprecation') directly, the same "call config()
 * straight from an Application-layer method, no constructor injection of
 * the array" style EnforceRateLimitAction already establishes for
 * config('mcp.rate_limit_per_minute') — there's exactly one caller
 * (Infrastructure\Middleware\ApiVersioning) and no test needs to swap this
 * config out for something other than a real config() value.
 */
final class DeprecationNotifier implements DeprecationNotifierInterface
{
    public function isDeprecated(ApiVersion $version): bool
    {
        return $this->entryFor($version) !== null;
    }

    public function sunsetDateFor(ApiVersion $version): ?SunsetDate
    {
        $entry = $this->entryFor($version);

        return $entry !== null ? SunsetDate::fromString($entry['sunset_at']) : null;
    }

    public function successorVersionFor(ApiVersion $version): ?ApiVersion
    {
        $entry = $this->entryFor($version);

        return $entry !== null ? ApiVersion::tryFrom($entry['successor']) : null;
    }

    public function migrationGuideUrlFor(ApiVersion $version): ?string
    {
        return $this->entryFor($version)['migration_guide'] ?? null;
    }

    public function warningMessageFor(ApiVersion $version): ?string
    {
        $entry = $this->entryFor($version);

        if ($entry === null) {
            return null;
        }

        return sprintf(
            'API %s is deprecated. Please migrate to %s by %s',
            $version->value,
            $entry['successor'],
            $entry['sunset_at'],
        );
    }

    /**
     * @return array{deprecated_at: string, sunset_at: string, successor: string, migration_guide: string}|null
     */
    private function entryFor(ApiVersion $version): ?array
    {
        return config("api.deprecation.{$version->value}");
    }
}

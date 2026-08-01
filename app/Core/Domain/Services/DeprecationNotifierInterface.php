<?php

namespace App\Core\Domain\Services;

use App\Core\Domain\ValueObjects\ApiVersion;
use App\Core\Domain\ValueObjects\SunsetDate;

/**
 * Pure decision service answering "is this version deprecated, and what
 * should the client be told about it" — the same
 * "only combines/decides, never fetches or writes anything itself" shape
 * NotificationDispatcher::shouldSend() already establishes. The concrete
 * Application-layer DeprecationNotifier is the one place that actually
 * reads config('api.deprecation') and, one layer further out,
 * Infrastructure\Middleware\ApiVersioning is the one place that turns
 * these decisions into real HTTP response headers and a log line — this
 * interface itself never touches a Response, a header, or a logger.
 */
interface DeprecationNotifierInterface
{
    public function isDeprecated(ApiVersion $version): bool;

    public function sunsetDateFor(ApiVersion $version): ?SunsetDate;

    public function successorVersionFor(ApiVersion $version): ?ApiVersion;

    public function migrationGuideUrlFor(ApiVersion $version): ?string;

    public function warningMessageFor(ApiVersion $version): ?string;
}

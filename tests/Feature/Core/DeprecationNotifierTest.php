<?php

namespace Tests\Feature\Core;

use App\Core\Application\Services\DeprecationNotifier;
use App\Core\Domain\ValueObjects\ApiVersion;
use Tests\TestCase;

/**
 * DeprecationNotifier reads config('api.deprecation') directly (the same
 * "call config() straight from an Application-layer method" style
 * EnforceRateLimitAction already established, per its own docblock) — it
 * needs a booted container to resolve config(), the same reason
 * MCPRateLimitTest is a Feature test rather than a plain PHPUnit one.
 */
class DeprecationNotifierTest extends TestCase
{
    public function test_isDeprecated_forVersionWithAConfiguredEntry_returnsTrue(): void
    {
        $notifier = app(DeprecationNotifier::class);

        $this->assertTrue($notifier->isDeprecated(ApiVersion::V1));
    }

    public function test_isDeprecated_forVersionWithNoConfiguredEntry_returnsFalse(): void
    {
        $notifier = app(DeprecationNotifier::class);

        $this->assertFalse($notifier->isDeprecated(ApiVersion::V2));
    }

    public function test_sunsetDateFor_returnsTheConfiguredSunsetDate(): void
    {
        $notifier = app(DeprecationNotifier::class);

        $this->assertSame('Sat, 01 Jan 2028 00:00:00 GMT', $notifier->sunsetDateFor(ApiVersion::V1)->toHttpDate());
    }

    public function test_sunsetDateFor_withNoConfiguredEntry_returnsNull(): void
    {
        $notifier = app(DeprecationNotifier::class);

        $this->assertNull($notifier->sunsetDateFor(ApiVersion::V2));
    }

    public function test_successorVersionFor_returnsTheConfiguredSuccessor(): void
    {
        $notifier = app(DeprecationNotifier::class);

        $this->assertSame(ApiVersion::V2, $notifier->successorVersionFor(ApiVersion::V1));
    }

    public function test_migrationGuideUrlFor_returnsTheConfiguredUrl(): void
    {
        $notifier = app(DeprecationNotifier::class);

        $this->assertSame(
            'https://docs.opencommerce.ir/migration/v1-to-v2',
            $notifier->migrationGuideUrlFor(ApiVersion::V1),
        );
    }

    public function test_warningMessageFor_mentionsTheSuccessorAndSunsetDate(): void
    {
        $notifier = app(DeprecationNotifier::class);

        $message = $notifier->warningMessageFor(ApiVersion::V1);

        $this->assertStringContainsString('v2', $message);
        $this->assertStringContainsString('2028-01-01', $message);
    }

    public function test_customDeprecationConfig_isHonored(): void
    {
        config(['api.deprecation.v2' => [
            'deprecated_at' => '2030-01-01',
            'sunset_at' => '2031-01-01',
            'successor' => 'v3',
            'migration_guide' => 'https://docs.opencommerce.ir/migration/v2-to-v3',
        ]]);

        $notifier = app(DeprecationNotifier::class);

        $this->assertTrue($notifier->isDeprecated(ApiVersion::V2));
        $this->assertSame(ApiVersion::V3, $notifier->successorVersionFor(ApiVersion::V2));
    }
}

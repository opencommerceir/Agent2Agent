<?php

namespace Tests\Unit\Core;

use App\Core\Application\Services\CapabilityHandlerRegistry;
use App\Core\Domain\Exceptions\CapabilityNotFoundException;
use PHPUnit\Framework\TestCase;

class CapabilityHandlerRegistryTest extends TestCase
{
    public function test_register_thenGetHandler_returnsTheSameCallable(): void
    {
        $registry = new CapabilityHandlerRegistry();
        $handler = fn (array $input) => ['echo' => $input['message']];

        $registry->register('demo.echo', $handler);

        $this->assertSame($handler, $registry->getHandler('demo.echo'));
    }

    public function test_hasHandler_beforeRegistration_returnsFalse(): void
    {
        $registry = new CapabilityHandlerRegistry();

        $this->assertFalse($registry->hasHandler('demo.echo'));
    }

    public function test_hasHandler_afterRegistration_returnsTrue(): void
    {
        $registry = new CapabilityHandlerRegistry();
        $registry->register('demo.echo', fn (array $input) => $input);

        $this->assertTrue($registry->hasHandler('demo.echo'));
    }

    public function test_getHandler_forUnregisteredCapability_throwsCapabilityNotFoundException(): void
    {
        $registry = new CapabilityHandlerRegistry();

        $this->expectException(CapabilityNotFoundException::class);
        $registry->getHandler('demo.nonexistent');
    }
}

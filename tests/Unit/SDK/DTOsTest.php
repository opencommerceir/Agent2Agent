<?php

namespace Tests\Unit\SDK;

use App\SDK\DTOs\Capability;
use App\SDK\DTOs\CapabilityInput;
use App\SDK\DTOs\CapabilityOutput;
use PHPUnit\Framework\TestCase;

class DTOsTest extends TestCase
{
    public function test_capability_fromArray_hydratesAllFields(): void
    {
        $capability = Capability::fromArray([
            'name' => 'commerce.product.search',
            'description' => 'Search products',
            'inputSchema' => ['query' => 'string'],
            'outputSchema' => ['products' => 'array'],
            'requiredPermissions' => ['commerce.products.read'],
        ]);

        $this->assertSame('commerce.product.search', $capability->name);
        $this->assertSame(['query' => 'string'], $capability->inputSchema);
        $this->assertSame(['commerce.products.read'], $capability->requiredPermissions);
    }

    public function test_capability_fromArray_withMissingOptionalFields_usesDefaults(): void
    {
        $capability = Capability::fromArray(['name' => 'commerce.product.search']);

        $this->assertSame('', $capability->description);
        $this->assertSame([], $capability->inputSchema);
        $this->assertSame([], $capability->requiredPermissions);
    }

    public function test_capabilityInput_getAndHas_readValuesByKey(): void
    {
        $input = CapabilityInput::fromArray(['query' => 'laptop', 'limit' => 10]);

        $this->assertTrue($input->has('query'));
        $this->assertFalse($input->has('missing'));
        $this->assertSame('laptop', $input->get('query'));
        $this->assertNull($input->get('missing'));
        $this->assertSame('fallback', $input->get('missing', 'fallback'));
        $this->assertSame(['query' => 'laptop', 'limit' => 10], $input->toArray());
    }

    public function test_capabilityOutput_get_readsValuesFromResultData(): void
    {
        $output = CapabilityOutput::fromArray(['products' => ['a', 'b'], 'total' => 2]);

        $this->assertSame(['a', 'b'], $output->get('products'));
        $this->assertSame(2, $output->get('total'));
        $this->assertNull($output->get('missing'));
        $this->assertSame(['products' => ['a', 'b'], 'total' => 2], $output->toArray());
    }
}

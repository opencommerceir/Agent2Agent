<?php

namespace Tests\Unit\Core;

use App\Core\Domain\Entities\Permission;
use App\Core\Domain\Exceptions\InvalidPermissionKeyException;
use App\Core\Domain\ValueObjects\PermissionKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PermissionTest extends TestCase
{
    public function test_permissionKey_withValidFormat_isAccepted(): void
    {
        $key = new PermissionKey('commerce.products.read');

        $this->assertSame('commerce.products.read', $key->value());
        $this->assertSame('commerce.products.read', (string) $key);
    }

    #[DataProvider('invalidKeyProvider')]
    public function test_permissionKey_withInvalidFormat_throwsInvalidPermissionKeyException(string $invalid): void
    {
        $this->expectException(InvalidPermissionKeyException::class);

        new PermissionKey($invalid);
    }

    public static function invalidKeyProvider(): array
    {
        return [
            'missing a segment' => ['commerce.read'],
            'uppercase letters' => ['Commerce.Products.Read'],
            'no dots at all' => ['commerceproductsread'],
            'trailing dot' => ['commerce.products.'],
            'leading digit in segment' => ['1commerce.products.read'],
            'empty string' => [''],
        ];
    }

    public function test_permissionKey_equals_returnsTrueForSameValue(): void
    {
        $a = new PermissionKey('commerce.products.read');
        $b = new PermissionKey('commerce.products.read');

        $this->assertTrue($a->equals($b));
    }

    public function test_permissionKey_equals_returnsFalseForDifferentValue(): void
    {
        $a = new PermissionKey('commerce.products.read');
        $b = new PermissionKey('commerce.orders.create');

        $this->assertFalse($a->equals($b));
    }

    public function test_permission_create_wrapsKeyAndDescription(): void
    {
        $permission = Permission::create(new PermissionKey('commerce.orders.create'), 'Create orders');

        $this->assertNull($permission->id());
        $this->assertSame('commerce.orders.create', $permission->key()->value());
        $this->assertSame('Create orders', $permission->description());
    }

    public function test_permission_create_withoutDescription_allowsNullDescription(): void
    {
        $permission = Permission::create(new PermissionKey('commerce.orders.create'));

        $this->assertNull($permission->description());
    }
}

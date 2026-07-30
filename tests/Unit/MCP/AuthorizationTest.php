<?php

namespace Tests\Unit\MCP;

use App\Core\Application\Actions\CheckPermissionAction;
use App\Core\Domain\Entities\Role;
use App\Core\Domain\Exceptions\PermissionDeniedException;
use App\Core\Domain\Repositories\MemberRoleRepositoryInterface;
use App\Core\Domain\ValueObjects\MemberType;
use App\Core\Domain\ValueObjects\PermissionKey;
use DateTimeImmutable;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class AuthorizationTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @param list<string> $permissionStrings
     */
    private function roleWithPermissions(array $permissionStrings): Role
    {
        return new Role(
            id: 1,
            tenantId: 1,
            name: 'Store Manager',
            slug: 'store-manager',
            description: null,
            permissions: array_map(fn (string $p) => new PermissionKey($p), $permissionStrings),
            createdAt: new DateTimeImmutable(),
        );
    }

    public function test_execute_whenMemberHasPermissionThroughRole_returnsTrue(): void
    {
        $role = $this->roleWithPermissions(['commerce.products.read']);

        $memberRoles = Mockery::mock(MemberRoleRepositoryInterface::class);
        $memberRoles->shouldReceive('findRolesForMember')
            ->once()->with(MemberType::Agent, 5, 1)
            ->andReturn([$role]);

        $allowed = (new CheckPermissionAction($memberRoles))->execute(MemberType::Agent, 5, 1, 'commerce.products.read');

        $this->assertTrue($allowed);
    }

    public function test_execute_whenMemberRoleLacksPermission_returnsFalse(): void
    {
        $role = $this->roleWithPermissions(['commerce.products.read']);

        $memberRoles = Mockery::mock(MemberRoleRepositoryInterface::class);
        $memberRoles->shouldReceive('findRolesForMember')->once()->andReturn([$role]);

        $allowed = (new CheckPermissionAction($memberRoles))->execute(MemberType::Agent, 5, 1, 'commerce.orders.delete');

        $this->assertFalse($allowed);
    }

    public function test_execute_whenMemberHasNoRolesAtAll_returnsFalse(): void
    {
        $memberRoles = Mockery::mock(MemberRoleRepositoryInterface::class);
        $memberRoles->shouldReceive('findRolesForMember')->once()->andReturn([]);

        $allowed = (new CheckPermissionAction($memberRoles))->execute(MemberType::Agent, 5, 1, 'commerce.products.read');

        $this->assertFalse($allowed);
    }

    public function test_execute_whenOneOfMultipleRolesGrantsPermission_returnsTrue(): void
    {
        $roleWithout = $this->roleWithPermissions(['commerce.orders.create']);
        $roleWith = $this->roleWithPermissions(['commerce.products.read']);

        $memberRoles = Mockery::mock(MemberRoleRepositoryInterface::class);
        $memberRoles->shouldReceive('findRolesForMember')->once()->andReturn([$roleWithout, $roleWith]);

        $allowed = (new CheckPermissionAction($memberRoles))->execute(MemberType::Agent, 5, 1, 'commerce.products.read');

        $this->assertTrue($allowed);
    }

    public function test_authorize_whenPermissionDenied_throwsPermissionDeniedException(): void
    {
        $memberRoles = Mockery::mock(MemberRoleRepositoryInterface::class);
        $memberRoles->shouldReceive('findRolesForMember')->once()->andReturn([]);

        $this->expectException(PermissionDeniedException::class);

        (new CheckPermissionAction($memberRoles))->authorize(MemberType::Agent, 5, 1, 'commerce.products.read');
    }

    public function test_authorize_whenPermissionGranted_doesNotThrow(): void
    {
        $role = $this->roleWithPermissions(['commerce.products.read']);

        $memberRoles = Mockery::mock(MemberRoleRepositoryInterface::class);
        $memberRoles->shouldReceive('findRolesForMember')->once()->andReturn([$role]);

        (new CheckPermissionAction($memberRoles))->authorize(MemberType::Agent, 5, 1, 'commerce.products.read');

        $this->assertTrue(true, 'authorize() completed without throwing');
    }
}

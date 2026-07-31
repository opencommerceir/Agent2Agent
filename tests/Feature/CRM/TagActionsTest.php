<?php

namespace Tests\Feature\CRM;

use App\Core\Application\Actions\CreateTenantAction;
use App\Modules\Commerce\Application\Actions\CreateCustomerAction;
use App\Modules\CRM\Application\Actions\AssignTagToCustomerAction;
use App\Modules\CRM\Application\Actions\CreateTagAction;
use App\Modules\CRM\Domain\Exceptions\CustomerNotFoundException;
use App\Modules\CRM\Domain\Exceptions\TagNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * CreateTagAction/AssignTagToCustomerAction exercised directly — neither
 * is wired to any MCP capability this stage (see CreateTagAction's own
 * docblock).
 */
class TagActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_createTag_withValidData_persistsTag(): void
    {
        $tenantId = $this->createTenant();

        $tag = app(CreateTagAction::class)->execute($tenantId, 'VIP', '#ff0000');

        $this->assertNotNull($tag->id);
        $this->assertSame('VIP', $tag->name);
        $this->assertSame('#ff0000', $tag->color);
        $this->assertDatabaseHas('tags', ['tenant_id' => $tenantId, 'name' => 'VIP']);
    }

    public function test_createTag_withDuplicateNameInSameTenant_throwsInvalidArgumentException(): void
    {
        $tenantId = $this->createTenant();
        app(CreateTagAction::class)->execute($tenantId, 'VIP');

        $this->expectException(InvalidArgumentException::class);

        app(CreateTagAction::class)->execute($tenantId, 'VIP');
    }

    public function test_createTag_withSameNameInDifferentTenants_doesNotConflict(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        app(CreateTagAction::class)->execute($tenantA, 'VIP');
        $tag = app(CreateTagAction::class)->execute($tenantB, 'VIP');

        $this->assertNotNull($tag->id);
        $this->assertDatabaseCount('tags', 2);
    }

    public function test_assignTagToCustomer_withValidIds_createsAssignment(): void
    {
        $tenantId = $this->createTenant();
        $customer = app(CreateCustomerAction::class)->execute($tenantId, 'Jane', 'Doe', 'jane-'.Str::random(8).'@example.com');
        $tag = app(CreateTagAction::class)->execute($tenantId, 'VIP');

        app(AssignTagToCustomerAction::class)->execute($tenantId, $customer->id, $tag->id);

        $this->assertTrue(
            DB::table('customer_tag')->where('customer_id', $customer->id)->where('tag_id', $tag->id)->exists()
        );
    }

    public function test_assignTagToCustomer_calledTwice_doesNotDuplicateAssignment(): void
    {
        $tenantId = $this->createTenant();
        $customer = app(CreateCustomerAction::class)->execute($tenantId, 'Jane', 'Doe', 'jane-'.Str::random(8).'@example.com');
        $tag = app(CreateTagAction::class)->execute($tenantId, 'VIP');

        app(AssignTagToCustomerAction::class)->execute($tenantId, $customer->id, $tag->id);
        app(AssignTagToCustomerAction::class)->execute($tenantId, $customer->id, $tag->id);

        $this->assertSame(
            1,
            DB::table('customer_tag')->where('customer_id', $customer->id)->where('tag_id', $tag->id)->count()
        );
    }

    public function test_assignTagToCustomer_withNonexistentTag_throwsTagNotFoundException(): void
    {
        $tenantId = $this->createTenant();
        $customer = app(CreateCustomerAction::class)->execute($tenantId, 'Jane', 'Doe', 'jane-'.Str::random(8).'@example.com');

        $this->expectException(TagNotFoundException::class);

        app(AssignTagToCustomerAction::class)->execute($tenantId, $customer->id, 999999);
    }

    public function test_assignTagToCustomer_withNonexistentCustomer_throwsCustomerNotFoundException(): void
    {
        $tenantId = $this->createTenant();
        $tag = app(CreateTagAction::class)->execute($tenantId, 'VIP');

        $this->expectException(CustomerNotFoundException::class);

        app(AssignTagToCustomerAction::class)->execute($tenantId, 999999, $tag->id);
    }

    private function createTenant(): int
    {
        return app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid())->id;
    }
}

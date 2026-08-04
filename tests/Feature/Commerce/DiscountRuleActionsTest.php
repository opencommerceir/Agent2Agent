<?php

namespace Tests\Feature\Commerce;

use App\Core\Application\Actions\CreateTenantAction;
use App\Modules\Commerce\Application\Actions\CreateDiscountRuleAction;
use App\Modules\Commerce\Application\Actions\DeleteDiscountRuleAction;
use App\Modules\Commerce\Application\Actions\GetDiscountRuleAction;
use App\Modules\Commerce\Application\Actions\ListDiscountRulesAction;
use App\Modules\Commerce\Application\Actions\UpdateDiscountRuleAction;
use App\Modules\Commerce\Domain\Exceptions\DiscountRuleNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscountRuleActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_thenGet_returnsTheSameRuleWithItsConditions(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());

        $rule = app(CreateDiscountRuleAction::class)->execute(
            tenantId: $tenant->id,
            name: '10% off',
            discountType: 'percentage',
            discountValue: 10,
            priority: 10,
            stackability: 'stackable',
            conditions: [['type' => 'min_quantity', 'value' => 2]],
        );

        $this->assertSame('10% off', $rule->name);
        $this->assertCount(1, $rule->conditions);
        $this->assertTrue($rule->isActive);

        $fetched = app(GetDiscountRuleAction::class)->execute($rule->id, $tenant->id);
        $this->assertSame($rule->id, $fetched->id);
        $this->assertCount(1, $fetched->conditions);
    }

    public function test_get_forRuleInAnotherTenant_throwsNotFound(): void
    {
        $tenantA = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $tenantB = app(CreateTenantAction::class)->execute('Beta Inc', 'beta-'.uniqid());

        $rule = app(CreateDiscountRuleAction::class)->execute($tenantA->id, '10% off', 'percentage', 10, 10, 'stackable');

        $this->expectException(DiscountRuleNotFoundException::class);

        app(GetDiscountRuleAction::class)->execute($rule->id, $tenantB->id);
    }

    public function test_update_changesEditableFields_leavesConditionsUntouched(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $rule = app(CreateDiscountRuleAction::class)->execute(
            $tenant->id, '10% off', 'percentage', 10, 10, 'stackable',
            conditions: [['type' => 'min_quantity', 'value' => 2]],
        );

        $updated = app(UpdateDiscountRuleAction::class)->execute(
            id: $rule->id,
            tenantId: $tenant->id,
            name: '15% off',
            discountValue: 15,
            priority: 5,
            stackability: 'exclusive',
            isActive: false,
        );

        $this->assertSame('15% off', $updated->name);
        $this->assertSame(15, $updated->discountValue);
        $this->assertSame('exclusive', $updated->stackability);
        $this->assertFalse($updated->isActive);
        $this->assertCount(1, $updated->conditions);
    }

    public function test_delete_removesTheRule(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $rule = app(CreateDiscountRuleAction::class)->execute($tenant->id, '10% off', 'percentage', 10, 10, 'stackable');

        app(DeleteDiscountRuleAction::class)->execute($rule->id, $tenant->id);

        $this->expectException(DiscountRuleNotFoundException::class);
        app(GetDiscountRuleAction::class)->execute($rule->id, $tenant->id);
    }

    public function test_listByTenant_onlyReturnsThatTenantsRules_orderedByPriority(): void
    {
        $tenantA = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $tenantB = app(CreateTenantAction::class)->execute('Beta Inc', 'beta-'.uniqid());

        app(CreateDiscountRuleAction::class)->execute($tenantA->id, 'Low priority', 'percentage', 5, 1, 'stackable');
        app(CreateDiscountRuleAction::class)->execute($tenantA->id, 'High priority', 'percentage', 10, 10, 'stackable');
        app(CreateDiscountRuleAction::class)->execute($tenantB->id, 'Other tenant', 'percentage', 10, 10, 'stackable');

        $rules = app(ListDiscountRulesAction::class)->execute($tenantA->id);

        $this->assertCount(2, $rules);
        $this->assertSame('High priority', $rules[0]->name);
    }
}

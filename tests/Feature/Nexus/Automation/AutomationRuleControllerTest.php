<?php

namespace Tests\Feature\Nexus\Automation;

use App\Domains\Nexus\Automation\Application\Actions\CreateInventoryAlertRuleAction;
use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Domains\Nexus\Catalog\Application\Actions\AddProductAction;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomationRuleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_withoutLogin_redirectsToLogin(): void
    {
        $response = $this->get(route('nexus.automation.index'));

        $response->assertRedirect(route('nexus.business.login'));
    }

    public function test_index_rendersRulesList(): void
    {
        $owner = $this->verifiedBusinessWithOwner('Caller Co');

        $response = $this->actingAs($owner, 'business')->get(route('nexus.automation.index'));

        $response->assertOk();
        $response->assertViewHas('rules');
    }

    public function test_store_createsInventoryAlertRule(): void
    {
        $owner = $this->verifiedBusinessWithOwner('Caller Co');
        $product = app(AddProductAction::class)->execute($owner->business_id, 'محصول', 'Widget', 10_000, 'IRT', 5);

        $response = $this->actingAs($owner, 'business')->post(route('nexus.automation.store'), [
            'type' => 'inventory_alert',
            'product_id' => $product->id,
            'threshold_quantity' => 2,
        ]);

        $response->assertRedirect(route('nexus.automation.index'));
        $this->assertDatabaseHas('nexus_automation_rules', ['business_id' => $owner->business_id, 'type' => 'inventory_alert']);
    }

    public function test_pauseAndResume_toggleStatus(): void
    {
        $owner = $this->verifiedBusinessWithOwner('Caller Co');
        $product = app(AddProductAction::class)->execute($owner->business_id, 'محصول', 'Widget', 10_000, 'IRT', 5);
        $rule = app(CreateInventoryAlertRuleAction::class)->execute($owner->business_id, $product->id, 2);

        $this->actingAs($owner, 'business')->post(route('nexus.automation.pause', $rule->id))->assertRedirect();
        $this->assertDatabaseHas('nexus_automation_rules', ['id' => $rule->id, 'status' => 'paused']);

        $this->actingAs($owner, 'business')->post(route('nexus.automation.resume', $rule->id))->assertRedirect();
        $this->assertDatabaseHas('nexus_automation_rules', ['id' => $rule->id, 'status' => 'active']);
    }

    public function test_destroy_deletesRule(): void
    {
        $owner = $this->verifiedBusinessWithOwner('Caller Co');
        $product = app(AddProductAction::class)->execute($owner->business_id, 'محصول', 'Widget', 10_000, 'IRT', 5);
        $rule = app(CreateInventoryAlertRuleAction::class)->execute($owner->business_id, $product->id, 2);

        $this->actingAs($owner, 'business')->delete(route('nexus.automation.destroy', $rule->id))->assertRedirect();

        $this->assertDatabaseMissing('nexus_automation_rules', ['id' => $rule->id]);
    }

    private function verifiedBusinessWithOwner(string $nameEn): BusinessOwner
    {
        $business = $this->verifiedBusiness($nameEn);

        return BusinessOwner::query()->create([
            'business_id' => $business->id,
            'name' => 'Owner',
            'email' => strtolower(str_replace(' ', '', $nameEn)).'@example.com',
            'password' => 'password123',
        ]);
    }

    private function verifiedBusiness(string $nameEn): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);
        app(GrantCreditsAction::class)->execute($business->id, 100_000, CreditTransactionType::AdminGrant, 'test.seed');

        return $business;
    }
}

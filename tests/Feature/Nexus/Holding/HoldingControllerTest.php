<?php

namespace Tests\Feature\Nexus\Holding;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Domains\Nexus\Holding\Application\Actions\CreateHoldingAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HoldingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_createsHoldingAndRedirectsToShow(): void
    {
        $parent = $this->verifiedBusinessWithOwner('Parent Co');

        $response = $this->actingAs($parent['owner'], 'business')->post(route('nexus.holding.store'), [
            'name_fa' => 'هلدینگ الف',
            'name_en' => 'Alpha Holding',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('nexus_holdings', ['parent_business_id' => $parent['business']->id]);
    }

    public function test_show_displaysHoldingDetails(): void
    {
        $parent = $this->verifiedBusinessWithOwner('Parent Co');
        $holding = app(CreateHoldingAction::class)->execute($parent['business']->id, 'هلدینگ الف', 'Alpha Holding');

        $response = $this->actingAs($parent['owner'], 'business')->get(route('nexus.holding.show', $holding->id));

        $response->assertOk();
        $response->assertSee('Alpha Holding');
    }

    public function test_invite_byParent_succeeds(): void
    {
        $parent = $this->verifiedBusinessWithOwner('Parent Co');
        $sub = $this->verifiedBusiness('Sub Co');
        $holding = app(CreateHoldingAction::class)->execute($parent['business']->id, 'الف', 'Alpha');

        $response = $this->actingAs($parent['owner'], 'business')->post(route('nexus.holding.invite', $holding->id), [
            'target_business_id' => $sub->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('nexus_holding_subsidiaries', ['holding_id' => $holding->id, 'business_id' => $sub->id, 'status' => 'invited']);
    }

    public function test_index_withoutLogin_redirectsToLogin(): void
    {
        $response = $this->get(route('nexus.holding.index'));

        $response->assertRedirect(route('nexus.business.login'));
    }

    public function test_show_byNonMember_isForbidden(): void
    {
        $parent = $this->verifiedBusinessWithOwner('Parent Co');
        $outsider = $this->verifiedBusinessWithOwner('Outsider Co');
        $holding = app(CreateHoldingAction::class)->execute($parent['business']->id, 'الف', 'Alpha');

        $response = $this->actingAs($outsider['owner'], 'business')->get(route('nexus.holding.show', $holding->id));

        $response->assertForbidden();
    }

    public function test_togglePooling_byParent_succeeds(): void
    {
        $parent = $this->verifiedBusinessWithOwner('Parent Co');
        $holding = app(CreateHoldingAction::class)->execute($parent['business']->id, 'الف', 'Alpha');

        $response = $this->actingAs($parent['owner'], 'business')->post(route('nexus.holding.pooling.toggle', $holding->id), [
            'enabled' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('nexus_holdings', ['id' => $holding->id, 'credit_pooling_enabled' => true]);
    }

    private function verifiedBusiness(string $nameEn, int $credits = 0): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);

        return $business;
    }

    private function verifiedBusinessWithOwner(string $nameEn): array
    {
        $business = $this->verifiedBusiness($nameEn);
        $owner = BusinessOwner::query()->create([
            'business_id' => $business->id,
            'name' => 'Owner',
            'email' => strtolower(str_replace(' ', '', $nameEn)).'@example.com',
            'password' => 'password123',
        ]);

        return ['business' => $business, 'owner' => $owner];
    }
}

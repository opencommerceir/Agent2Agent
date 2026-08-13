<?php

namespace Tests\Feature\Nexus\Developer;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Developer\Application\Actions\PublishAgentStrategyTemplateAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentMarketplaceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_withoutLogin_redirectsToLogin(): void
    {
        $this->get(route('nexus.developer.agent-marketplace.index'))
            ->assertRedirect(route('nexus.business.login'));
    }

    public function test_publish_createsTemplate(): void
    {
        $owner = $this->verifiedBusinessWithOwner('Publisher Co');

        $response = $this->actingAs($owner, 'business')->post(route('nexus.developer.agent-marketplace.publish'), [
            'name_fa' => 'قالب',
            'name_en' => 'Template',
            'description_fa' => 'توضیح',
            'description_en' => 'Description',
            'personality' => 'friendly',
            'tone' => 'formal',
            'strategies_json' => '{"opening_discount_percent": 5}',
            'price_credits' => 100,
        ]);

        $response->assertRedirect(route('nexus.developer.agent-marketplace.index'));
        $this->assertDatabaseHas('nexus_agent_strategy_templates', ['publisher_business_id' => $owner->business_id, 'name_en' => 'Template']);
    }

    public function test_preview_returnsJsonWithoutAuth_stillRequiresLogin(): void
    {
        $owner = $this->verifiedBusinessWithOwner('Publisher Co');
        $template = app(PublishAgentStrategyTemplateAction::class)->execute(
            $owner->business_id, 'قالب', 'Template', 'توضیح', 'Description', 'friendly', 'formal', ['opening_discount_percent' => 5], 100,
        );

        $response = $this->actingAs($owner, 'business')->getJson(route('nexus.developer.agent-marketplace.preview', $template->id));

        $response->assertOk();
        $response->assertJsonPath('personality', 'friendly');
    }

    public function test_install_appliesTemplateAndRedirectsWithStatus(): void
    {
        $publisherOwner = $this->verifiedBusinessWithOwner('Publisher Co');
        $installerOwner = $this->verifiedBusinessWithOwner('Installer Co');
        $template = app(PublishAgentStrategyTemplateAction::class)->execute(
            $publisherOwner->business_id, 'قالب', 'Template', 'توضیح', 'Description', 'friendly', 'formal', ['opening_discount_percent' => 5], 0,
        );

        $response = $this->actingAs($installerOwner, 'business')->post(route('nexus.developer.agent-marketplace.install', $template->id));

        $response->assertRedirect(route('nexus.developer.agent-marketplace.index'));
        $response->assertSessionHas('status');
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

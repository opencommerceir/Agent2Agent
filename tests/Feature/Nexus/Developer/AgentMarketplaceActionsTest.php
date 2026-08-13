<?php

namespace Tests\Feature\Nexus\Developer;

use App\Domains\Nexus\Agent\Domain\Repositories\AgentRepositoryInterface;
use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\Repositories\CreditBalanceRepositoryInterface;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Developer\Application\Actions\InstallAgentStrategyTemplateAction;
use App\Domains\Nexus\Developer\Application\Actions\ListMarketplaceTemplatesAction;
use App\Domains\Nexus\Developer\Application\Actions\PreviewAgentStrategyTemplateAction;
use App\Domains\Nexus\Developer\Application\Actions\PublishAgentStrategyTemplateAction;
use App\Domains\Nexus\Developer\Application\Actions\UnpublishAgentStrategyTemplateAction;
use App\Domains\Nexus\Developer\Domain\Repositories\AgentTemplateInstallRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AgentMarketplaceActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_thenAppearsInMarketplace(): void
    {
        $publisher = $this->verifiedBusiness('Publisher Co');

        $this->publish($publisher->id, 100);

        $listings = app(ListMarketplaceTemplatesAction::class)->execute();
        $this->assertCount(1, $listings);
        $this->assertSame(100, $listings[0]->priceCredits);
    }

    public function test_preview_returnsShapeWithoutChargingOrPersisting(): void
    {
        $publisher = $this->verifiedBusiness('Publisher Co');
        $template = $this->publish($publisher->id, 100);

        $preview = app(PreviewAgentStrategyTemplateAction::class)->execute($template->id);

        $this->assertSame('friendly', $preview['personality']);
        $this->assertSame(['opening_discount_percent' => 5], $preview['strategies']);
        $this->assertSame(0, app(ListMarketplaceTemplatesAction::class)->execute()[0]->installCount);
    }

    public function test_install_crossBusiness_splitsRevenueAndAppliesToAgent(): void
    {
        $publisher = $this->verifiedBusiness('Publisher Co');
        $installer = $this->verifiedBusiness('Installer Co');
        $template = $this->publish($publisher->id, 1000);

        app(InstallAgentStrategyTemplateAction::class)->execute($installer->id, $template->id);

        $balances = app(CreditBalanceRepositoryInterface::class);
        $this->assertSame(99_000, $balances->findByBusinessId($installer->id)->balance()); // 100,000 - 1,000
        $this->assertSame(100_800, $balances->findByBusinessId($publisher->id)->balance()); // 100,000 + 800 (20% platform fee on 1000)

        $installerAgent = app(AgentRepositoryInterface::class)->findByBusinessId($installer->id);
        $this->assertSame('friendly', $installerAgent->personality());
        $this->assertSame(['opening_discount_percent' => 5], $installerAgent->strategies());

        $this->assertSame(1, app(ListMarketplaceTemplatesAction::class)->execute()[0]->installCount);

        $installs = app(AgentTemplateInstallRepositoryInterface::class)->findByInstallingBusinessId($installer->id);
        $this->assertCount(1, $installs);
        $this->assertSame(1000, $installs[0]->priceCredits());
        $this->assertSame(200, $installs[0]->platformFeeCredits());
        $this->assertSame(800, $installs[0]->publisherEarningsCredits());
    }

    public function test_install_ownTemplate_isFreeAndUnledgeredAmounts(): void
    {
        $publisher = $this->verifiedBusiness('Publisher Co');
        $template = $this->publish($publisher->id, 1000);
        $balanceBefore = app(CreditBalanceRepositoryInterface::class)->findByBusinessId($publisher->id)->balance();

        app(InstallAgentStrategyTemplateAction::class)->execute($publisher->id, $template->id);

        $balanceAfter = app(CreditBalanceRepositoryInterface::class)->findByBusinessId($publisher->id)->balance();
        $this->assertSame($balanceBefore, $balanceAfter);

        $installs = app(AgentTemplateInstallRepositoryInterface::class)->findByInstallingBusinessId($publisher->id);
        $this->assertSame(0, $installs[0]->priceCredits());
        $this->assertSame(0, $installs[0]->publisherEarningsCredits());
    }

    public function test_install_revokedTemplate_throws(): void
    {
        $publisher = $this->verifiedBusiness('Publisher Co');
        $installer = $this->verifiedBusiness('Installer Co');
        $template = $this->publish($publisher->id, 1000);
        app(UnpublishAgentStrategyTemplateAction::class)->execute($template->id, $publisher->id);

        $this->expectException(InvalidArgumentException::class);

        app(InstallAgentStrategyTemplateAction::class)->execute($installer->id, $template->id);
    }

    public function test_unpublish_someoneElses_throws(): void
    {
        $publisher = $this->verifiedBusiness('Publisher Co');
        $intruder = $this->verifiedBusiness('Intruder Co');
        $template = $this->publish($publisher->id, 1000);

        $this->expectException(InvalidArgumentException::class);

        app(UnpublishAgentStrategyTemplateAction::class)->execute($template->id, $intruder->id);
    }

    private function publish(int $publisherBusinessId, int $priceCredits): \App\Domains\Nexus\Developer\Application\DTOs\AgentStrategyTemplateData
    {
        return app(PublishAgentStrategyTemplateAction::class)->execute(
            publisherBusinessId: $publisherBusinessId,
            nameFa: 'قالب',
            nameEn: 'Template',
            descriptionFa: 'توضیح',
            descriptionEn: 'Description',
            personality: 'friendly',
            tone: 'formal',
            strategies: ['opening_discount_percent' => 5],
            priceCredits: $priceCredits,
        );
    }

    private function verifiedBusiness(string $nameEn): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);
        app(GrantCreditsAction::class)->execute($business->id, 100_000, CreditTransactionType::AdminGrant, 'test.seed');

        return $business;
    }
}

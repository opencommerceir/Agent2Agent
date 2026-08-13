<?php

namespace App\Domains\Nexus\Developer\Application\Actions;

use App\Domains\Nexus\Admin\Application\Services\MarginSettingsService;
use App\Domains\Nexus\Agent\Domain\Repositories\AgentRepositoryInterface;
use App\Domains\Nexus\Credit\Application\Actions\DeductCreditsAction;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Developer\Domain\Entities\AgentTemplateInstall;
use App\Domains\Nexus\Developer\Domain\Repositories\AgentStrategyTemplateRepositoryInterface;
use App\Domains\Nexus\Developer\Domain\Repositories\AgentTemplateInstallRepositoryInterface;
use InvalidArgumentException;

/**
 * The real revenue-sharing install (Phase 9/M7) — applies a template to
 * the installing Business's own Agent and, for a genuine cross-business
 * install, moves credits: the installer pays `priceCredits`, the
 * publisher earns `priceCredits - platformFee` (snapshotted at install
 * time via MarginSettingsService::agentTemplateFeePercent(), same
 * "compute once, apply durably later" rule Escrow::hold() already
 * established — a later admin fee change never re-prices a past install).
 * A publisher installing their own template pays nothing and earns
 * nothing (there is no third party to share revenue with) — still
 * ledgered (all three amounts zero) so a publisher's own install history
 * stays complete.
 */
final class InstallAgentStrategyTemplateAction
{
    public function __construct(
        private readonly AgentStrategyTemplateRepositoryInterface $templates,
        private readonly AgentTemplateInstallRepositoryInterface $installs,
        private readonly AgentRepositoryInterface $agents,
        private readonly GrantCreditsAction $grantCredits,
        private readonly DeductCreditsAction $deductCredits,
        private readonly MarginSettingsService $marginSettings,
    ) {
    }

    public function execute(int $installingBusinessId, int $templateId): void
    {
        $template = $this->templates->findById($templateId);

        if (! $template || $template->isRevoked()) {
            throw new InvalidArgumentException("AgentStrategyTemplate [{$templateId}] is not available.");
        }

        $agent = $this->agents->findByBusinessId($installingBusinessId);

        if (! $agent) {
            throw new InvalidArgumentException("Business [{$installingBusinessId}] has no Agent yet.");
        }

        $isSelfInstall = $template->publisherBusinessId() === $installingBusinessId;
        $priceCredits = $isSelfInstall ? 0 : $template->priceCredits();
        $platformFeeCredits = 0;
        $publisherEarningsCredits = 0;

        if ($priceCredits > 0) {
            $this->deductCredits->execute($installingBusinessId, $priceCredits, 'developer.agent_template.install', $template->id());

            $platformFeeCredits = (int) round($priceCredits * $this->marginSettings->agentTemplateFeePercent() / 100);
            $publisherEarningsCredits = $priceCredits - $platformFeeCredits;

            if ($publisherEarningsCredits > 0) {
                $this->grantCredits->execute(
                    $template->publisherBusinessId(), $publisherEarningsCredits, CreditTransactionType::AgentTemplateEarning,
                    'developer.agent_template.earning', $template->id(),
                );
            }
        }

        if ($template->personality() !== null && $template->tone() !== null) {
            $agent->updatePersonality($template->personality(), $template->tone());
        }
        $agent->setStrategies($template->strategies());
        $this->agents->save($agent);

        $template->recordInstall();
        $this->templates->save($template);

        $this->installs->save(AgentTemplateInstall::record(
            templateId: $template->id(),
            installingBusinessId: $installingBusinessId,
            publisherBusinessId: $template->publisherBusinessId(),
            priceCredits: $priceCredits,
            platformFeeCredits: $platformFeeCredits,
            publisherEarningsCredits: $publisherEarningsCredits,
        ));
    }
}

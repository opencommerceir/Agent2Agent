<?php

namespace App\Domains\Nexus\Automation\Application\Actions;

use App\Domains\Nexus\Automation\Domain\Entities\AutomationRule;
use App\Domains\Nexus\Automation\Domain\Entities\AutomationRunLog;
use App\Domains\Nexus\Automation\Domain\Repositories\AutomationRuleRepositoryInterface;
use App\Domains\Nexus\Automation\Domain\Repositories\AutomationRunLogRepositoryInterface;
use App\Domains\Nexus\Automation\Domain\ValueObjects\AutomationRuleType;
use App\Domains\Nexus\Automation\Domain\ValueObjects\AutomationRunOutcome;
use App\Domains\Nexus\Automation\Domain\ValueObjects\PriceAlertDirection;
use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Domains\Nexus\Catalog\Domain\Repositories\ProductRepositoryInterface;
use App\Domains\Nexus\Catalog\Domain\Repositories\ServiceRepositoryInterface;
use App\Domains\Nexus\Marketplace\Application\Actions\SearchMarketplaceAction;
use App\Domains\Nexus\Negotiation\Application\Actions\InitiateNegotiationAction;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;
use App\Modules\Notifications\Application\Actions\SendNotificationAction;
use App\Modules\Notifications\Domain\ValueObjects\ChannelType;
use App\Modules\Notifications\Domain\ValueObjects\NotificationType;
use App\Modules\Notifications\Domain\ValueObjects\Recipient;
use DateTimeImmutable;
use Throwable;

/**
 * The Automation engine (Phase 8/M4) — evaluates every Active AutomationRule
 * once per invocation and triggers the ones that are due. Two real entry
 * points, same shape DetectFraudSignalsAction/DetectFraudSignalsCommand
 * (Phase 6/M4) already established: `ProcessAutomationRulesCommand`
 * scheduled hourly in routes/console.php, and this Action itself callable
 * directly (tests, an eventual "run now" admin button).
 *
 * A single rule's failure (a deleted counterparty, an expired credit
 * balance, a network error) is caught and logged as `Failed` — it never
 * aborts the whole run, so one broken rule can't silently stop every other
 * Business's automation from firing.
 */
final class ProcessAutomationRulesAction
{
    public function __construct(
        private readonly AutomationRuleRepositoryInterface $rules,
        private readonly AutomationRunLogRepositoryInterface $runLogs,
        private readonly BusinessRepositoryInterface $businesses,
        private readonly ProductRepositoryInterface $products,
        private readonly ServiceRepositoryInterface $services,
        private readonly InitiateNegotiationAction $initiateNegotiation,
        private readonly SearchMarketplaceAction $searchMarketplace,
        private readonly SendNotificationAction $sendNotification,
    ) {
    }

    /**
     * @return array{triggered: int, failed: int}
     */
    public function execute(): array
    {
        $triggered = 0;
        $failed = 0;
        $now = new DateTimeImmutable();

        foreach ($this->rules->findActive() as $rule) {
            try {
                if ($this->process($rule, $now)) {
                    $triggered++;
                }
            } catch (Throwable $e) {
                $this->runLogs->save(AutomationRunLog::record($rule->id(), $rule->businessId(), AutomationRunOutcome::Failed, $e->getMessage()));
                $failed++;
            }
        }

        return ['triggered' => $triggered, 'failed' => $failed];
    }

    private function process(AutomationRule $rule, DateTimeImmutable $now): bool
    {
        return match ($rule->type()) {
            AutomationRuleType::RecurringOrder => $this->processRecurringOrder($rule, $now),
            AutomationRuleType::InventoryAlert => $this->processInventoryAlert($rule, $now),
            AutomationRuleType::PriceAlert => $this->processPriceAlert($rule, $now),
        };
    }

    private function processRecurringOrder(AutomationRule $rule, DateTimeImmutable $now): bool
    {
        $config = $rule->config();

        if (! $rule->canRetriggerAt($now, $config['intervalDays'] * 24)) {
            return false;
        }

        $negotiation = $this->initiateNegotiation->execute(
            initiatorBusinessId: $rule->businessId(),
            counterpartyBusinessId: $config['counterpartyBusinessId'],
            catalogItemType: CatalogItemType::from($config['catalogItemType']),
            catalogItemId: $config['catalogItemId'],
            terms: new NegotiationTerms(
                Money::fromAmount($config['priceAmount'], $config['priceCurrency']),
                $config['quantity'],
                'Nexus Automation: recurring order',
            ),
        );

        $this->markTriggered($rule, $now, "Negotiation #{$negotiation->id} opened with Business #{$config['counterpartyBusinessId']}.");
        $this->notify($rule->businessId(), NotificationType::RecurringOrderPlaced, 'Recurring order placed', "Your recurring order rule opened Negotiation #{$negotiation->id}.");

        return true;
    }

    private function processInventoryAlert(AutomationRule $rule, DateTimeImmutable $now): bool
    {
        $config = $rule->config();
        $product = $this->products->findById($config['productId']);

        if (! $product || $product->businessId() !== $rule->businessId()) {
            return false; // deleted/moved since the rule was created — nothing honest to alert on
        }

        if ($product->stockQuantity() > $config['thresholdQuantity']) {
            return false; // condition not met
        }

        if (! $rule->canRetriggerAt($now, (int) config('nexus.platform.automation.alert_cooldown_hours'))) {
            return false;
        }

        $searchResult = $this->searchMarketplace->execute($rule->businessId(), $product->nameEn());
        $candidateCount = count($searchResult['listings']);

        $this->markTriggered($rule, $now, "Stock at {$product->stockQuantity()} (threshold {$config['thresholdQuantity']}); {$candidateCount} candidate supplier(s) found.");
        $this->notify(
            $rule->businessId(),
            NotificationType::InventoryAlertTriggered,
            'Low inventory alert',
            "\"{$product->nameEn()}\" stock is at {$product->stockQuantity()} (threshold {$config['thresholdQuantity']}). Found {$candidateCount} candidate supplier(s) on the marketplace.",
        );

        return true;
    }

    private function processPriceAlert(AutomationRule $rule, DateTimeImmutable $now): bool
    {
        $config = $rule->config();
        $type = CatalogItemType::from($config['catalogItemType']);

        $currentPrice = $type === CatalogItemType::Product
            ? $this->products->findById($config['catalogItemId'])?->price()
            : $this->services->findById($config['catalogItemId'])?->hourlyPrice();

        if ($currentPrice === null || $currentPrice->currency() !== $config['currency']) {
            return false; // item deleted, or currency changed since the rule was created
        }

        $direction = PriceAlertDirection::from($config['direction']);

        if (! $direction->isMet($currentPrice->amount(), $config['targetPriceAmount'])) {
            return false;
        }

        if (! $rule->canRetriggerAt($now, (int) config('nexus.platform.automation.alert_cooldown_hours'))) {
            return false;
        }

        $this->markTriggered($rule, $now, "Price {$currentPrice->amount()} {$currentPrice->currency()} crossed target {$config['targetPriceAmount']} ({$direction->value}).");
        $this->notify(
            $rule->businessId(),
            NotificationType::PriceAlertTriggered,
            'Price alert triggered',
            "The watched {$type->value} #{$config['catalogItemId']} price is now {$currentPrice->amount()} {$currentPrice->currency()}, crossing your target of {$config['targetPriceAmount']}.",
        );

        return true;
    }

    private function markTriggered(AutomationRule $rule, DateTimeImmutable $now, string $detail): void
    {
        $rule->recordTrigger($now);
        $this->rules->save($rule);
        $this->runLogs->save(AutomationRunLog::record($rule->id(), $rule->businessId(), AutomationRunOutcome::Triggered, $detail));
    }

    /**
     * Best-effort — a missing/inactive Email channel makes
     * SendNotificationAction a silent no-op (its own dispatcher rule,
     * same reasoning SendAgentInviteAction's own docblock already
     * documents); the AutomationRunLog row above is the honest record
     * that the rule fired, independent of whether the email actually
     * reached anyone.
     */
    private function notify(int $businessId, NotificationType $type, string $subject, string $body): void
    {
        $business = $this->businesses->findById($businessId);
        $owner = BusinessOwner::query()->where('business_id', $businessId)->first();

        if (! $business || ! $owner) {
            return;
        }

        $this->sendNotification->execute(
            tenantId: $business->tenantId(),
            type: $type,
            channelType: ChannelType::Email,
            recipient: new Recipient($owner->email),
            subject: $subject,
            body: $body,
        );
    }
}

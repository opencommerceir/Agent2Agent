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
use App\Domains\Nexus\Marketplace\Application\Actions\GetRecommendationsAction;
use App\Domains\Nexus\Marketplace\Application\Actions\SearchMarketplaceAction;
use App\Domains\Nexus\Negotiation\Application\Actions\InitiateNegotiationAction;
use App\Domains\Nexus\Negotiation\Domain\Repositories\NegotiationRepositoryInterface;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationStatus;
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
        private readonly GetRecommendationsAction $getRecommendations,
        private readonly NegotiationRepositoryInterface $negotiations,
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
            AutomationRuleType::AutoDiscover => $this->processAutoDiscover($rule, $now),
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

    /**
     * The proactive half of the Autonomous Agent Runtime — no
     * pre-configured counterparty (unlike processRecurringOrder above):
     * GetRecommendationsAction (existing, reputation-ranked, same-industry)
     * finds candidates fresh each run. Opens at most one Negotiation per
     * run, on the first candidate that (a) actually has a verified,
     * matching-currency catalog item and (b) isn't already mid-negotiation
     * with this Business — never spams every recommended candidate at once.
     *
     * Cooldown is a flat 1 hour (`canRetriggerAt` only supports whole-hour
     * granularity — a pre-existing constraint on the entity shared with
     * RecurringOrder/InventoryAlert/PriceAlert, not something worth
     * widening just for this one caller). This governs how often a single
     * Business's rule goes looking for a *new* counterparty — it has
     * nothing to do with response speed once a conversation is already
     * open, which AutoRespondToNegotiationListener handles synchronously,
     * in milliseconds, completely independent of this schedule.
     */
    private function processAutoDiscover(AutomationRule $rule, DateTimeImmutable $now): bool
    {
        $config = $rule->config();
        $cooldownHours = (int) config('nexus.platform.automation.auto_discover_cooldown_hours', 1);

        if (! $rule->canRetriggerAt($now, $cooldownHours)) {
            return false;
        }

        $itemType = CatalogItemType::from($config['catalogItemType']);
        $recommendations = $this->getRecommendations->execute($rule->businessId(), 5);

        // Consume the cooldown regardless of outcome — GetRecommendationsAction
        // above already spent real credits this run; without this, a
        // Business with no viable candidates would re-spend on every single
        // scheduler tick instead of waiting out the same cooldown a match
        // would have earned.
        $rule->recordTrigger($now);
        $this->rules->save($rule);

        foreach ($recommendations['listings'] as $listing) {
            $candidateId = $listing['businessId'];

            if ($this->hasOpenNegotiationWith($rule->businessId(), $candidateId)) {
                continue;
            }

            $item = $itemType === CatalogItemType::Product
                ? ($this->products->findByBusinessId($candidateId)[0] ?? null)
                : ($this->services->findByBusinessId($candidateId)[0] ?? null);

            $itemPrice = $itemType === CatalogItemType::Product ? $item?->price() : $item?->hourlyPrice();

            if (! $item || ! $item->isVerified() || ! $itemPrice || $itemPrice->currency() !== $config['priceCurrency']) {
                continue;
            }

            // Open below both my own ceiling and their list price, leaving
            // real room to negotiate rather than opening at my hard limit.
            $offerPrice = (int) round(min($config['maxPriceAmount'], $itemPrice->amount()) * 0.9);

            $negotiation = $this->initiateNegotiation->execute(
                initiatorBusinessId: $rule->businessId(),
                counterpartyBusinessId: $candidateId,
                catalogItemType: $itemType,
                catalogItemId: $item->id(),
                terms: new NegotiationTerms(Money::fromAmount($offerPrice, $config['priceCurrency']), $config['quantity'], 'Nexus Automation: auto-discover'),
            );

            $this->runLogs->save(AutomationRunLog::record($rule->id(), $rule->businessId(), AutomationRunOutcome::Triggered, "Negotiation #{$negotiation->id} opened with discovered Business #{$candidateId}."));
            $this->notify($rule->businessId(), NotificationType::AutoDiscoverMatched, 'Auto-discovered a negotiation partner', "Your Agent found a matching counterparty and opened Negotiation #{$negotiation->id}.");

            return true;
        }

        return false;
    }

    private function hasOpenNegotiationWith(int $businessId, int $counterpartyId): bool
    {
        $openStatuses = [NegotiationStatus::Proposed, NegotiationStatus::Countered, NegotiationStatus::PendingApproval];

        foreach ($this->negotiations->findVisibleTo($businessId) as $negotiation) {
            if ($negotiation->otherParty($businessId) === $counterpartyId && in_array($negotiation->status(), $openStatuses, true)) {
                return true;
            }
        }

        return false;
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

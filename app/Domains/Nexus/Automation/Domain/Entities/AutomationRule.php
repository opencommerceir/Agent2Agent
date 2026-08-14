<?php

namespace App\Domains\Nexus\Automation\Domain\Entities;

use App\Domains\Nexus\Automation\Domain\Exceptions\InvalidAutomationRuleStateException;
use App\Domains\Nexus\Automation\Domain\ValueObjects\AutomationRuleStatus;
use App\Domains\Nexus\Automation\Domain\ValueObjects\AutomationRuleType;
use App\Domains\Nexus\Automation\Domain\ValueObjects\PriceAlertDirection;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A business-owned, scheduled workflow rule (Phase 8/M4, roadmap:
 * "Automation Workflows"). Framework-free (Domain Layer Rules).
 *
 * **Three named rule shapes, not a generic rule engine.** The roadmap names
 * exactly three workflow types ("سفارشات تکرارشونده، هشدار موجودی
 * (auto-search)، هشدار قیمت") — this entity models exactly those three via
 * `AutomationRuleType`, with type-specific fields in one JSON `$config` bag
 * (same escape-hatch convention Catalog's own Product/Service `attributes`
 * already established) rather than a column per type or an open-ended
 * condition/action grammar a real "workflow builder" implies. The portal
 * page presenting this (Phase 8/M4) is honestly a guided, type-specific
 * form — not a drag-and-drop canvas, since no diagramming JS dependency
 * exists in this codebase (same "no new JS dependency" restraint Network
 * Visualization, Phase 5/M4, already applied to its own "visual" feature).
 *
 * State machine: `Active -> Paused -> Active`, same explicit
 * ALLOWED_TRANSITIONS + guarded transitionTo() shape every other Nexus
 * aggregate uses, just a two-state toggle rather than a longer chain.
 *
 * `lastTriggeredAt` is the one piece of mutable state ProcessAutomationRulesAction
 * (the engine) needs to decide whether a rule is due again — the entity
 * itself has no opinion on *whether current conditions warrant* a trigger
 * (that requires live Catalog/Negotiation data this entity doesn't hold),
 * only on *whether enough time has passed* since the last one, exactly the
 * same division of responsibility `Negotiation::requestApproval()` draws
 * between "the entity records the fact" and "the Application layer decides
 * the business rule" (Phase 2/M3's own docblock).
 */
final class AutomationRule
{
    /**
     * @var array<string, list<AutomationRuleStatus>>
     */
    private const ALLOWED_TRANSITIONS = [
        'active' => [AutomationRuleStatus::Paused],
        'paused' => [AutomationRuleStatus::Active],
    ];

    public function __construct(
        private readonly ?int $id,
        private readonly int $businessId,
        private readonly AutomationRuleType $type,
        private readonly array $config,
        private AutomationRuleStatus $status,
        private ?DateTimeImmutable $lastTriggeredAt,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function forRecurringOrder(
        int $businessId,
        int $counterpartyBusinessId,
        CatalogItemType $catalogItemType,
        int $catalogItemId,
        int $priceAmount,
        string $priceCurrency,
        int $quantity,
        int $intervalDays,
    ): self {
        if ($businessId === $counterpartyBusinessId) {
            throw new InvalidArgumentException('A recurring order cannot target the same Business.');
        }

        if ($intervalDays < 1) {
            throw new InvalidArgumentException("intervalDays must be at least 1, got [{$intervalDays}].");
        }

        if ($quantity < 1) {
            throw new InvalidArgumentException("quantity must be at least 1, got [{$quantity}].");
        }

        return self::create($businessId, AutomationRuleType::RecurringOrder, [
            'counterpartyBusinessId' => $counterpartyBusinessId,
            'catalogItemType' => $catalogItemType->value,
            'catalogItemId' => $catalogItemId,
            'priceAmount' => $priceAmount,
            'priceCurrency' => $priceCurrency,
            'quantity' => $quantity,
            'intervalDays' => $intervalDays,
        ]);
    }

    public static function forInventoryAlert(int $businessId, int $productId, int $thresholdQuantity): self
    {
        if ($thresholdQuantity < 0) {
            throw new InvalidArgumentException("thresholdQuantity cannot be negative, got [{$thresholdQuantity}].");
        }

        return self::create($businessId, AutomationRuleType::InventoryAlert, [
            'productId' => $productId,
            'thresholdQuantity' => $thresholdQuantity,
        ]);
    }

    public static function forPriceAlert(
        int $businessId,
        CatalogItemType $catalogItemType,
        int $catalogItemId,
        int $targetPriceAmount,
        string $currency,
        PriceAlertDirection $direction,
    ): self {
        if ($targetPriceAmount < 0) {
            throw new InvalidArgumentException("targetPriceAmount cannot be negative, got [{$targetPriceAmount}].");
        }

        return self::create($businessId, AutomationRuleType::PriceAlert, [
            'catalogItemType' => $catalogItemType->value,
            'catalogItemId' => $catalogItemId,
            'targetPriceAmount' => $targetPriceAmount,
            'currency' => $currency,
            'direction' => $direction->value,
        ]);
    }

    public static function forAutoDiscover(
        int $businessId,
        CatalogItemType $catalogItemType,
        int $maxPriceAmount,
        string $priceCurrency,
        int $quantity,
    ): self {
        if ($maxPriceAmount < 1) {
            throw new InvalidArgumentException("maxPriceAmount must be at least 1, got [{$maxPriceAmount}].");
        }

        if ($quantity < 1) {
            throw new InvalidArgumentException("quantity must be at least 1, got [{$quantity}].");
        }

        return self::create($businessId, AutomationRuleType::AutoDiscover, [
            'catalogItemType' => $catalogItemType->value,
            'maxPriceAmount' => $maxPriceAmount,
            'priceCurrency' => $priceCurrency,
            'quantity' => $quantity,
        ]);
    }

    private static function create(int $businessId, AutomationRuleType $type, array $config): self
    {
        return new self(
            id: null,
            businessId: $businessId,
            type: $type,
            config: $config,
            status: AutomationRuleStatus::Active,
            lastTriggeredAt: null,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function pause(): void
    {
        $this->transitionTo(AutomationRuleStatus::Paused);
    }

    public function resume(): void
    {
        $this->transitionTo(AutomationRuleStatus::Active);
    }

    public function recordTrigger(DateTimeImmutable $at): void
    {
        $this->lastTriggeredAt = $at;
    }

    /**
     * Has enough time passed since the last trigger (or has it never
     * triggered) that a newly-met condition is allowed to fire again? Purely
     * a cooldown check — the caller (ProcessAutomationRulesAction) is
     * responsible for having already confirmed the underlying condition
     * (stock below threshold, price crossed target, interval elapsed) is
     * true right now.
     */
    public function canRetriggerAt(DateTimeImmutable $now, int $cooldownHours): bool
    {
        if ($this->lastTriggeredAt === null) {
            return true;
        }

        return $now >= $this->lastTriggeredAt->modify("+{$cooldownHours} hours");
    }

    private function transitionTo(AutomationRuleStatus $newStatus): void
    {
        $allowed = self::ALLOWED_TRANSITIONS[$this->status->value];

        if (! in_array($newStatus, $allowed, true)) {
            throw new InvalidAutomationRuleStateException(
                "AutomationRule cannot transition from [{$this->status->value}] to [{$newStatus->value}]."
            );
        }

        $this->status = $newStatus;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function businessId(): int
    {
        return $this->businessId;
    }

    public function type(): AutomationRuleType
    {
        return $this->type;
    }

    public function config(): array
    {
        return $this->config;
    }

    public function status(): AutomationRuleStatus
    {
        return $this->status;
    }

    public function lastTriggeredAt(): ?DateTimeImmutable
    {
        return $this->lastTriggeredAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}

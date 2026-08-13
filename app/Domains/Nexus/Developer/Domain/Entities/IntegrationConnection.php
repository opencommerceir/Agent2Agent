<?php

namespace App\Domains\Nexus\Developer\Domain\Entities;

use App\Domains\Nexus\Developer\Domain\ValueObjects\IntegrationCategory;
use DateTimeImmutable;

/**
 * A Business's own configured outbound sync target — "Integration
 * Marketplace" (docs/nexus-roadmap.md: "کانکتورهای آماده (ERP, CRM,
 * Accounting, Logistics)") honestly means one generic, category-tagged
 * connector here, not named vendor-specific integrations: this codebase
 * has no SAP/QuickBooks/HubSpot/etc. credentials or SDKs to wire to (the
 * same honesty SAML/LDAP stubs, Phase 7/M8, already established for a
 * roadmap line naming things this dev environment can't actually reach).
 * `fieldMapping` (sourceField => targetField) is the roadmap's "No-code
 * builder" — a plain key/value form in the portal, not a drag-and-drop
 * canvas (no diagramming dependency exists in this codebase, the same
 * restraint Network Visualization/Automation Workflows already applied).
 * Zapier/Make.com connectivity is honestly served by the Webhook
 * subscription system (Phase 9/M3) instead of a redundant second
 * mechanism here — both platforms' own "custom webhook" trigger is
 * exactly "give me a URL to POST events to."
 */
final class IntegrationConnection
{
    /**
     * @param array<string, string> $fieldMapping
     */
    public function __construct(
        private readonly ?int $id,
        private readonly int $businessId,
        private readonly IntegrationCategory $category,
        private readonly string $name,
        private readonly string $targetUrl,
        private readonly ?string $authToken,
        private readonly array $fieldMapping,
        private ?DateTimeImmutable $revokedAt,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @param array<string, string> $fieldMapping
     */
    public static function create(
        int $businessId,
        IntegrationCategory $category,
        string $name,
        string $targetUrl,
        ?string $authToken,
        array $fieldMapping,
    ): self {
        return new self(
            id: null,
            businessId: $businessId,
            category: $category,
            name: $name,
            targetUrl: $targetUrl,
            authToken: $authToken,
            fieldMapping: $fieldMapping,
            revokedAt: null,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function revoke(): void
    {
        $this->revokedAt = new DateTimeImmutable();
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }

    /**
     * Applies the configured field mapping to one catalog item (a
     * Product/ServiceData::toArray() shape): only mapped keys pass
     * through, renamed to their target field name — an explicit
     * allow-list, not a rename-in-place of every field, so a Business
     * never accidentally leaks a field it didn't choose to map.
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    public function mapItem(array $item): array
    {
        if ($this->fieldMapping === []) {
            return $item;
        }

        $mapped = [];

        foreach ($this->fieldMapping as $source => $target) {
            if (array_key_exists($source, $item)) {
                $mapped[$target] = $item[$source];
            }
        }

        return $mapped;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function businessId(): int
    {
        return $this->businessId;
    }

    public function category(): IntegrationCategory
    {
        return $this->category;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function targetUrl(): string
    {
        return $this->targetUrl;
    }

    public function authToken(): ?string
    {
        return $this->authToken;
    }

    /**
     * @return array<string, string>
     */
    public function fieldMapping(): array
    {
        return $this->fieldMapping;
    }

    public function revokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}

<?php

namespace App\Core\Application\Actions;

use App\Core\Application\DTOs\TenantData;
use App\Core\Domain\Repositories\TenantRepositoryInterface;
use App\Core\Domain\ValueObjects\Language;
use InvalidArgumentException;

/**
 * Not wired to any MCP capability this stage — no capability for it was
 * requested, the same "built, tested, not yet exposed to Agents" gap every
 * prior module has carried at least one of (HANDOFF §6/§8.2, e.g. Finance's
 * own UpdateTaxRateAction). Exercised directly in
 * tests/Feature/Core/TenantDefaultLanguageTest.php, including the
 * end-to-end effect on LanguageDetector's own Tenant-default tier.
 */
final class SetTenantDefaultLanguageAction
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenants,
    ) {
    }

    public function execute(int $tenantId, string $language): TenantData
    {
        $tenant = $this->tenants->findById($tenantId);

        if ($tenant === null) {
            throw new InvalidArgumentException("Tenant [{$tenantId}] does not exist.");
        }

        $tenant->changeDefaultLanguage(Language::from($language));
        $tenant = $this->tenants->save($tenant);

        return TenantData::fromEntity($tenant);
    }
}

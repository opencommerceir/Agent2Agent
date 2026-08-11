<?php

namespace App\Domains\Nexus\Business\Application\Actions;

use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Domain\Repositories\TenantRepositoryInterface;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\Entities\Business;
use App\Domains\Nexus\Business\Domain\Events\BusinessWasRegistered;
use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * The first place Core's previously-unchained Tenant/Organization
 * primitives get composed together: CreateTenantAction -> CreateOrganizationAction
 * -> Business row. "هر Business = یک Tenant جدید" (docs/nexus-roadmap.md,
 * Phase 1) — reuses the existing multi-tenancy architecture rather than
 * building a parallel one (Extend, Don't Rebuild).
 *
 * Deliberately does NOT call Core's AddMemberToOrganizationAction: that
 * Action's MemberType enum is closed to User|Agent, both meaning a real
 * row in Core's own `users`/`agents` tables. A Business owner is a new,
 * Nexus-owned identity (`business_owners`, see the Business auth
 * milestone) with no row in `users` — recording it as MemberType::User
 * would misrepresent the polymorphic member_id to any code that trusts
 * that invariant (e.g. CheckPermissionAction). Ownership is tracked via
 * businesses.tenant_id/organization_id instead; Core's fine-grained RBAC
 * is not wired up for Business owners in Phase 1 (not asked for yet).
 */
final class RegisterBusinessAction
{
    public function __construct(
        private readonly CreateTenantAction $createTenant,
        private readonly CreateOrganizationAction $createOrganization,
        private readonly TenantRepositoryInterface $tenants,
        private readonly BusinessRepositoryInterface $businesses,
    ) {
    }

    public function execute(
        string $nameFa,
        string $nameEn,
        BusinessType $type,
        Industry $industry,
        ?string $logoPath = null,
        ?array $documents = null,
    ): BusinessData {
        $slug = $this->uniqueSlug($nameEn);

        $tenantData = $this->createTenant->execute($nameEn, $slug);
        $organizationData = $this->createOrganization->execute($tenantData->id, $nameEn, $slug);

        $business = Business::register(
            tenantId: $tenantData->id,
            organizationId: $organizationData->id,
            nameFa: $nameFa,
            nameEn: $nameEn,
            type: $type,
            industry: $industry,
            logoPath: $logoPath,
            documents: $documents,
        );
        $business = $this->businesses->save($business);

        Event::dispatch(new BusinessWasRegistered($business));

        return BusinessData::fromEntity($business);
    }

    private function uniqueSlug(string $nameEn): string
    {
        $base = Str::slug($nameEn);
        $slug = $base;
        $suffix = 1;

        while ($this->tenants->slugExists($slug)) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}

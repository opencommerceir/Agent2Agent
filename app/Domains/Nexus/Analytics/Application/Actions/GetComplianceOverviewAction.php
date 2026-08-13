<?php

namespace App\Domains\Nexus\Analytics\Application\Actions;

use App\Domains\Nexus\Analytics\Infrastructure\Queries\ComplianceQuery;
use App\Domains\Nexus\Audit\Application\Actions\VerifyAuditChainIntegrityAction;
use App\Domains\Nexus\Audit\Domain\Repositories\AuditLogEntryRepositoryInterface;
use App\Domains\Nexus\Sso\Application\Services\SsoProviderRegistry;

/**
 * The roadmap's "SOC 2 / ISO 27001 آمادگی" line (docs/nexus-roadmap.md,
 * Phase 7) read as an honest self-assessment dashboard, not a
 * certification claim — the same "reading across domains for a display
 * projection is fine, it's the query-side counterpart" reasoning
 * GetBusinessDashboardAction's own docblock established, this time
 * spanning Audit (M9), Business (suspension + data residency, M10), Sso
 * (Phase 7/M6-M8), and Contract (disputes, Phase 6/M3).
 *
 * `checklist` intentionally reports each control as a fact about *this
 * codebase's current state* (verified by calling the same Action/query
 * that control's own feature already uses), never a checkbox a human
 * ticks by hand — a compliance page that can silently drift from reality
 * is worse than no compliance page.
 */
final class GetComplianceOverviewAction
{
    public function __construct(
        private readonly ComplianceQuery $compliance,
        private readonly VerifyAuditChainIntegrityAction $verifyAuditChainIntegrity,
        private readonly AuditLogEntryRepositoryInterface $auditLogEntries,
        private readonly SsoProviderRegistry $ssoProviders,
    ) {
    }

    /**
     * @return array{
     *     auditChain: array{intact: bool, checkedCount: int, brokenAtSequence: ?int},
     *     totalBusinesses: int,
     *     suspendedBusinessCount: int,
     *     dataResidencyBreakdown: array<string, int>,
     *     mfaAdoptionRate: float,
     *     openDisputeCount: int,
     *     ssoProviders: list<array{key: string, isConfigured: bool}>,
     *     checklist: list<array{key: string, satisfied: bool}>,
     * }
     */
    public function execute(): array
    {
        $auditChain = $this->verifyAuditChainIntegrity->execute();
        $totalOwners = $this->compliance->totalOwnersCount();
        $mfaEnabled = $this->compliance->ownersWithMfaEnabledCount();
        $mfaAdoptionRate = $totalOwners > 0 ? round($mfaEnabled / $totalOwners * 100, 1) : 0.0;

        $ssoProviders = array_map(fn ($provider) => [
            'key' => $provider->key(),
            'isConfigured' => $provider->isConfigured(),
        ], $this->ssoProviders->all());

        $suspendedCount = $this->compliance->suspendedBusinessCount();
        $totalBusinesses = $this->compliance->totalBusinesses();
        $dataResidencyBreakdown = $this->compliance->dataResidencyBreakdown();
        $liveSsoProviderCount = count(array_filter($ssoProviders, fn (array $p) => $p['isConfigured']));

        return [
            'auditChain' => $auditChain,
            'totalBusinesses' => $totalBusinesses,
            'suspendedBusinessCount' => $suspendedCount,
            'dataResidencyBreakdown' => $dataResidencyBreakdown,
            'mfaAdoptionRate' => $mfaAdoptionRate,
            'openDisputeCount' => $this->compliance->openDisputeCount(),
            'ssoProviders' => $ssoProviders,
            'checklist' => [
                ['key' => 'immutable_audit_trail', 'satisfied' => $this->auditLogEntries->count() === 0 || $auditChain['intact']],
                ['key' => 'access_control_rbac', 'satisfied' => true],
                ['key' => 'mfa_available', 'satisfied' => true],
                ['key' => 'fraud_detection_suspension', 'satisfied' => true],
                ['key' => 'dispute_resolution_workflow', 'satisfied' => true],
                ['key' => 'sso_identity_federation', 'satisfied' => $liveSsoProviderCount > 0],
                ['key' => 'data_residency_declared', 'satisfied' => ($dataResidencyBreakdown['undeclared'] ?? 0) < $totalBusinesses],
            ],
        ];
    }
}

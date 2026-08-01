<?php

namespace App\Http\Controllers\Dashboard;

use App\Core\Application\Actions\SetTenantDefaultLanguageAction;
use App\Core\Domain\Repositories\TenantRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Only manages `default_language` — the one Tenant-level setting this
 * codebase actually has (Phase 4 Stage 4, §7.16's `SetTenantDefaultLanguageAction`).
 * Timezone/Currency were both named in this stage's own request, but
 * neither concept exists anywhere on Tenant (or anywhere else) yet —
 * adding them would mean inventing new Tenant fields/migrations under a
 * Dashboard-focused stage's time budget, not wiring an existing one.
 * Flagged as real, honest scope in HANDOFF rather than faked with fields
 * nothing else reads.
 */
class SettingsController extends Controller
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenants,
        private readonly SetTenantDefaultLanguageAction $setDefaultLanguage,
    ) {
    }

    public function index(Request $request): View
    {
        $tenants = $this->tenants->all();
        $tenantId = $request->integer('tenant_id') ?: (($tenants[0] ?? null)?->id());
        $tenant = $tenantId !== null ? $this->tenants->findById($tenantId) : null;

        return view('dashboard.settings.index', [
            'tenants' => $tenants,
            'tenant' => $tenant,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'integer'],
            'default_language' => ['required', 'in:en,fa'],
        ]);

        $this->setDefaultLanguage->execute($data['tenant_id'], $data['default_language']);

        return redirect()->route('dashboard.settings.index', ['tenant_id' => $data['tenant_id']])->with('status', t('messages.settings.saved'));
    }
}

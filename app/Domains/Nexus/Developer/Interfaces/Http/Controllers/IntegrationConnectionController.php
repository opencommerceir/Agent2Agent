<?php

namespace App\Domains\Nexus\Developer\Interfaces\Http\Controllers;

use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Domains\Nexus\Developer\Application\Actions\CreateIntegrationConnectionAction;
use App\Domains\Nexus\Developer\Application\Actions\ListIntegrationConnectionsAction;
use App\Domains\Nexus\Developer\Application\Actions\RevokeIntegrationConnectionAction;
use App\Domains\Nexus\Developer\Application\Actions\SyncCatalogToIntegrationAction;
use App\Domains\Nexus\Developer\Domain\Exceptions\IntegrationConnectionRevokedException;
use App\Domains\Nexus\Developer\Domain\Exceptions\IntegrationSyncFailedException;
use App\Domains\Nexus\Developer\Domain\ValueObjects\IntegrationCategory;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Business-portal Integration Marketplace management (Phase 9/M6) — same
 * single-page shape ApiKeyController/WebhookSubscriptionController (M1/M3)
 * already established.
 */
class IntegrationConnectionController extends Controller
{
    public function __construct(
        private readonly ListIntegrationConnectionsAction $listConnections,
        private readonly CreateIntegrationConnectionAction $createConnection,
        private readonly RevokeIntegrationConnectionAction $revokeConnection,
        private readonly SyncCatalogToIntegrationAction $syncCatalog,
    ) {
    }

    public function index(): View
    {
        return view('nexus::developer.integrations.index', [
            'connections' => $this->listConnections->execute($this->actingBusinessId()),
            'categories' => IntegrationCategory::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category' => ['required', 'string', 'in:'.implode(',', array_map(fn (IntegrationCategory $category) => $category->value, IntegrationCategory::cases()))],
            'name' => ['required', 'string', 'max:100'],
            'target_url' => ['required', 'url', 'max:2048'],
            'auth_token' => ['nullable', 'string', 'max:500'],
            'mapping_source' => ['array'],
            'mapping_source.*' => ['nullable', 'string', 'max:100'],
            'mapping_target' => ['array'],
            'mapping_target.*' => ['nullable', 'string', 'max:100'],
        ]);

        $fieldMapping = [];
        foreach ($validated['mapping_source'] ?? [] as $index => $source) {
            $target = $validated['mapping_target'][$index] ?? null;
            if ($source !== null && $source !== '' && $target !== null && $target !== '') {
                $fieldMapping[$source] = $target;
            }
        }

        $this->createConnection->execute(
            businessId: $this->actingBusinessId(),
            category: IntegrationCategory::from($validated['category']),
            name: $validated['name'],
            targetUrl: $validated['target_url'],
            authToken: $validated['auth_token'] ?? null,
            fieldMapping: $fieldMapping,
        );

        return redirect()->route('nexus.developer.integrations.index');
    }

    public function revoke(int $connection): RedirectResponse
    {
        $this->revokeConnection->execute($connection, $this->actingBusinessId());

        return redirect()->route('nexus.developer.integrations.index');
    }

    public function sync(int $connection): RedirectResponse
    {
        try {
            $result = $this->syncCatalog->execute($connection, $this->actingBusinessId());

            return redirect()->route('nexus.developer.integrations.index')
                ->with('status', t('messages.nexus.developer.integrations.sync_success', ['count' => $result['itemsSent']]));
        } catch (IntegrationConnectionRevokedException|IntegrationSyncFailedException $e) {
            return redirect()->route('nexus.developer.integrations.index')
                ->with('error', $e->getMessage());
        }
    }

    private function actingBusinessId(): int
    {
        /** @var BusinessOwner $owner */
        $owner = Auth::guard('business')->user();

        return $owner->business_id;
    }
}

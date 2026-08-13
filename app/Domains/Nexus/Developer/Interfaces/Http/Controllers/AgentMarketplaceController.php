<?php

namespace App\Domains\Nexus\Developer\Interfaces\Http\Controllers;

use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Domains\Nexus\Developer\Application\Actions\InstallAgentStrategyTemplateAction;
use App\Domains\Nexus\Developer\Application\Actions\ListMarketplaceTemplatesAction;
use App\Domains\Nexus\Developer\Application\Actions\ListMyPublishedTemplatesAction;
use App\Domains\Nexus\Developer\Application\Actions\PreviewAgentStrategyTemplateAction;
use App\Domains\Nexus\Developer\Application\Actions\PublishAgentStrategyTemplateAction;
use App\Domains\Nexus\Developer\Application\Actions\UnpublishAgentStrategyTemplateAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Business-portal Agent Developer Platform (Phase 9/M7) — browse/install
 * from the marketplace, publish/unpublish your own templates. Same
 * single-page shape the rest of the Developer domain's portal already
 * established.
 */
class AgentMarketplaceController extends Controller
{
    public function __construct(
        private readonly ListMarketplaceTemplatesAction $listMarketplaceTemplates,
        private readonly ListMyPublishedTemplatesAction $listMyPublishedTemplates,
        private readonly PublishAgentStrategyTemplateAction $publishTemplate,
        private readonly UnpublishAgentStrategyTemplateAction $unpublishTemplate,
        private readonly InstallAgentStrategyTemplateAction $installTemplate,
        private readonly PreviewAgentStrategyTemplateAction $previewTemplate,
        private readonly BusinessRepositoryInterface $businesses,
    ) {
    }

    public function index(Request $request): View
    {
        $listings = $this->listMarketplaceTemplates->execute($request->string('query')->toString() ?: null);

        $publisherNames = [];
        foreach ($listings as $listing) {
            if (! isset($publisherNames[$listing->publisherBusinessId])) {
                $business = $this->businesses->findById($listing->publisherBusinessId);
                $publisherNames[$listing->publisherBusinessId] = $business?->nameEn() ?? '—';
            }
        }

        return view('nexus::developer.agent-marketplace.index', [
            'listings' => $listings,
            'publisherNames' => $publisherNames,
            'myTemplates' => $this->listMyPublishedTemplates->execute($this->actingBusinessId()),
            'actingBusinessId' => $this->actingBusinessId(),
        ]);
    }

    public function preview(int $template): JsonResponse
    {
        return response()->json($this->previewTemplate->execute($template));
    }

    public function install(int $template): RedirectResponse
    {
        $this->installTemplate->execute($this->actingBusinessId(), $template);

        return redirect()->route('nexus.developer.agent-marketplace.index')
            ->with('status', t('messages.nexus.developer.agent_marketplace.installed'));
    }

    public function publish(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name_fa' => ['required', 'string', 'max:150'],
            'name_en' => ['required', 'string', 'max:150'],
            'description_fa' => ['required', 'string', 'max:2000'],
            'description_en' => ['required', 'string', 'max:2000'],
            'personality' => ['nullable', 'string', 'max:500'],
            'tone' => ['nullable', 'string', 'max:100'],
            'strategies_json' => ['required', 'json'],
            'price_credits' => ['required', 'integer', 'min:0'],
        ]);

        $this->publishTemplate->execute(
            publisherBusinessId: $this->actingBusinessId(),
            nameFa: $validated['name_fa'],
            nameEn: $validated['name_en'],
            descriptionFa: $validated['description_fa'],
            descriptionEn: $validated['description_en'],
            personality: $validated['personality'] ?? null,
            tone: $validated['tone'] ?? null,
            strategies: json_decode($validated['strategies_json'], true),
            priceCredits: (int) $validated['price_credits'],
        );

        return redirect()->route('nexus.developer.agent-marketplace.index');
    }

    public function unpublish(int $template): RedirectResponse
    {
        $this->unpublishTemplate->execute($template, $this->actingBusinessId());

        return redirect()->route('nexus.developer.agent-marketplace.index');
    }

    private function actingBusinessId(): int
    {
        /** @var BusinessOwner $owner */
        $owner = Auth::guard('business')->user();

        return $owner->business_id;
    }
}

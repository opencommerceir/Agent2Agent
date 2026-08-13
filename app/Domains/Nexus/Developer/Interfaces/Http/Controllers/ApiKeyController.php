<?php

namespace App\Domains\Nexus\Developer\Interfaces\Http\Controllers;

use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Domains\Nexus\Developer\Application\Actions\IssueApiKeyAction;
use App\Domains\Nexus\Developer\Application\Actions\ListApiKeysAction;
use App\Domains\Nexus\Developer\Application\Actions\RevokeApiKeyAction;
use App\Domains\Nexus\Developer\Domain\ValueObjects\ApiKeyScope;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Business-portal API key management (Phase 9/M1) — the human-operated
 * counterpart to the Public REST API (M2) these keys will authenticate
 * against. A single page (list + inline issue form), same shape
 * CreditPurchaseController already established for a simple business-
 * scoped feature. The plaintext key is flashed to the session once after
 * `store()` and rendered by the `index` view on the very next request
 * only — Laravel's flash lifecycle itself enforces "shown once".
 */
class ApiKeyController extends Controller
{
    public function __construct(
        private readonly ListApiKeysAction $listApiKeys,
        private readonly IssueApiKeyAction $issueApiKey,
        private readonly RevokeApiKeyAction $revokeApiKey,
    ) {
    }

    public function index(): View
    {
        return view('nexus::developer.api-keys.index', [
            'apiKeys' => $this->listApiKeys->execute($this->actingBusinessId()),
            'scopes' => ApiKeyScope::cases(),
            'plainKey' => session('plain_api_key'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:100'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['string', 'in:'.implode(',', array_map(fn (ApiKeyScope $scope) => $scope->value, ApiKeyScope::cases()))],
        ]);

        $result = $this->issueApiKey->execute(
            businessId: $this->actingBusinessId(),
            label: $validated['label'] ?? null,
            scopes: array_map(fn (string $value) => ApiKeyScope::from($value), $validated['scopes']),
        );

        return redirect()->route('nexus.developer.api-keys.index')
            ->with('plain_api_key', $result['plainKey']);
    }

    public function revoke(int $apiKey): RedirectResponse
    {
        $this->revokeApiKey->execute($apiKey, $this->actingBusinessId());

        return redirect()->route('nexus.developer.api-keys.index');
    }

    private function actingBusinessId(): int
    {
        /** @var BusinessOwner $owner */
        $owner = Auth::guard('business')->user();

        return $owner->business_id;
    }
}

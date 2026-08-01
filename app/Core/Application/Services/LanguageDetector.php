<?php

namespace App\Core\Application\Services;

use App\Core\Domain\Repositories\TenantRepositoryInterface;
use App\Core\Domain\ValueObjects\Language;
use Illuminate\Http\Request;

/**
 * The single place that decides "which language" for an incoming request,
 * per this stage's own priority order:
 *   1. ?lang= query parameter
 *   2. Accept-Language header (highest-q entry that matches a supported Language)
 *   3. the Tenant's own default_language, if a tenantId is known
 *   4. fallback: English
 *
 * Plain, container-autowired concrete class (no interface, no
 * ServiceProvider binding) — the same "there's exactly one way to do this"
 * reasoning Notifications' TemplateRenderer/Reporting's Query Builders
 * already establish for a service with no swappable implementation.
 */
final class LanguageDetector
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenants,
    ) {
    }

    public function detect(Request $request, ?int $tenantId = null): Language
    {
        $fromQuery = $request->query('lang');

        if (is_string($fromQuery) && ($language = Language::tryFrom($fromQuery)) !== null) {
            return $language;
        }

        $fromHeader = $this->fromAcceptLanguageHeader($request->header('Accept-Language'));

        if ($fromHeader !== null) {
            return $fromHeader;
        }

        return $tenantId !== null ? $this->detectForTenant($tenantId) : Language::English;
    }

    /**
     * The non-HTTP entry point: an event Listener reacting to a Domain
     * Event has a tenantId but no Request to read a query/header from —
     * only the Tenant-default/fallback-English tiers of the priority order
     * above apply.
     */
    public function detectForTenant(int $tenantId): Language
    {
        $tenant = $this->tenants->findById($tenantId);

        return $tenant?->defaultLanguage() ?? Language::English;
    }

    private function fromAcceptLanguageHeader(?string $header): ?Language
    {
        if ($header === null || trim($header) === '') {
            return null;
        }

        $entries = [];

        foreach (explode(',', $header) as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            [$tag, $q] = array_pad(explode(';q=', $part), 2, '1.0');
            $baseCode = strtolower(explode('-', trim($tag))[0]);

            $entries[] = ['code' => $baseCode, 'q' => (float) $q];
        }

        usort($entries, fn (array $a, array $b) => $b['q'] <=> $a['q']);

        foreach ($entries as $entry) {
            if (($language = Language::tryFrom($entry['code'])) !== null) {
                return $language;
            }
        }

        return null;
    }
}

<?php

namespace Tests\Feature\Core;

use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\SetTenantDefaultLanguageAction;
use App\Core\Application\Services\LanguageDetector;
use App\Core\Domain\ValueObjects\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Real container resolution (LanguageDetector depends on the real
 * TenantRepositoryInterface -> EloquentTenantRepository) — a Feature test,
 * not a plain PHPUnit one, since the Tenant-default tier needs a real
 * persisted Tenant. Covers this stage's own priority order: query
 * parameter -> Accept-Language header -> Tenant default -> English.
 */
class LanguageDetectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_detect_prefersQueryParameterOverEverythingElse(): void
    {
        $request = Request::create('/mcp/v1/execute?lang=fa', 'POST', [], server: [
            'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9',
        ]);

        $this->assertSame(Language::Persian, app(LanguageDetector::class)->detect($request));
    }

    public function test_detect_withNoQueryParameter_fallsBackToAcceptLanguageHeader(): void
    {
        $request = Request::create('/mcp/v1/execute', 'POST', [], server: [
            'HTTP_ACCEPT_LANGUAGE' => 'fa-IR,fa;q=0.9,en-US;q=0.8,en;q=0.7',
        ]);

        $this->assertSame(Language::Persian, app(LanguageDetector::class)->detect($request));
    }

    public function test_detect_withUnsupportedHeaderLanguage_skipsToNextTier(): void
    {
        $request = Request::create('/mcp/v1/execute', 'POST', [], server: [
            'HTTP_ACCEPT_LANGUAGE' => 'de-DE,de;q=0.9,fa;q=0.5',
        ]);

        // "de" isn't a supported Language; the next entry ("fa") is.
        $this->assertSame(Language::Persian, app(LanguageDetector::class)->detect($request));
    }

    public function test_detect_withNoQueryOrHeader_usesTenantDefault(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        app(SetTenantDefaultLanguageAction::class)->execute($tenant->id, 'fa');

        // Symfony's own Request::create() defaults HTTP_ACCEPT_LANGUAGE to
        // "en-us,en;q=0.5" when nothing overrides it — a real browser
        // always sends one, but a bare Agent/API client (this MCP
        // gateway's actual audience) may not, so the empty-string override
        // here simulates "no header sent at all", the case this tier
        // exists for.
        $request = Request::create('/mcp/v1/execute', 'POST', [], server: ['HTTP_ACCEPT_LANGUAGE' => '']);

        $this->assertSame(Language::Persian, app(LanguageDetector::class)->detect($request, $tenant->id));
    }

    public function test_detect_withNothingAvailable_fallsBackToEnglish(): void
    {
        $request = Request::create('/mcp/v1/execute', 'POST', [], server: ['HTTP_ACCEPT_LANGUAGE' => '']);

        $this->assertSame(Language::English, app(LanguageDetector::class)->detect($request));
    }

    public function test_detectForTenant_reflectsTenantDefaultChange(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $detector = app(LanguageDetector::class);

        $this->assertSame(Language::English, $detector->detectForTenant($tenant->id));

        app(SetTenantDefaultLanguageAction::class)->execute($tenant->id, 'fa');

        $this->assertSame(Language::Persian, $detector->detectForTenant($tenant->id));
    }
}

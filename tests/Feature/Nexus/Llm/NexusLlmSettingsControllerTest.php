<?php

namespace Tests\Feature\Nexus\Llm;

use App\Core\Application\Actions\CreateUserAction;
use App\Core\Infrastructure\Models\User;
use App\Domains\Nexus\Llm\Application\Services\LLMSettingsService;
use App\Domains\Nexus\Llm\Domain\ValueObjects\LLMFeature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NexusLlmSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        $data = app(CreateUserAction::class)->execute('Admin', 'admin-'.uniqid().'@example.com', 'password123', 'admin');

        return User::query()->find($data->id);
    }

    public function test_index_guestIsRedirectedToLogin(): void
    {
        $this->get(route('dashboard.nexus.llm-settings.index'))->assertRedirect('/login');
    }

    public function test_index_showsCurrentFeatureProviderMapping(): void
    {
        config(['nexus.platform.llm.feature_providers.reasoning' => 'qwen-14b-local']);
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get(route('dashboard.nexus.llm-settings.index'));

        $response->assertStatus(200);
        $response->assertSee('qwen-14b-local');
    }

    public function test_update_savesAndHotReloadsImmediately(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->put(route('dashboard.nexus.llm-settings.update'), [
            'feature_provider' => [
                'reasoning' => 'openai',
                'negotiation' => 'qwen-14b-local',
                'classification' => 'llama-3.2-3b-local',
                'fallback' => 'openrouter',
            ],
            'fallback_chain' => 'openrouter,groq',
            'daily_budget_per_agent_irt' => 75000,
            'monthly_budget_per_business_irt' => 1500000,
        ]);

        $response->assertRedirect(route('dashboard.nexus.llm-settings.index'));

        $settings = app(LLMSettingsService::class);
        $this->assertSame('openai', $settings->providerForFeature(LLMFeature::Reasoning));
        $this->assertSame(['openrouter', 'groq'], $settings->fallbackChain());
        $this->assertSame(75000, $settings->dailyBudgetPerAgentIrt());
        $this->assertSame(1500000, $settings->monthlyBudgetPerBusinessIrt());
    }

    public function test_update_withUnknownProviderId_returnsValidationErrorAndDoesNotPersist(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->put(route('dashboard.nexus.llm-settings.update'), [
            'feature_provider' => [
                'reasoning' => 'not-a-real-provider',
                'negotiation' => 'qwen-14b-local',
                'classification' => 'llama-3.2-3b-local',
                'fallback' => 'openrouter',
            ],
            'fallback_chain' => 'openrouter',
            'daily_budget_per_agent_irt' => 50000,
            'monthly_budget_per_business_irt' => 1000000,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('provider');
        $this->assertDatabaseMissing('nexus_platform_settings', ['key' => 'llm.feature_provider.reasoning', 'value' => 'not-a-real-provider']);
    }
}

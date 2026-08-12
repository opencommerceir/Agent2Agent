<?php

namespace Tests\Feature\Nexus\Llm;

use App\Core\Application\Actions\CreateUserAction;
use App\Core\Infrastructure\Models\User;
use App\Domains\Nexus\Llm\Application\Services\LLMProviderRegistry;
use App\Domains\Nexus\Llm\Domain\Exceptions\LLMProviderRequestFailedException;
use App\Domains\Nexus\Llm\Domain\Services\LLMProviderInterface;
use App\Domains\Nexus\Llm\Domain\ValueObjects\LLMFeature;
use App\Domains\Nexus\Llm\Domain\ValueObjects\LLMResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestConnectionEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        $data = app(CreateUserAction::class)->execute('Admin', 'admin-'.uniqid().'@example.com', 'password123', 'admin');

        return User::query()->find($data->id);
    }

    private function registerFakeProvider(string $name, bool $succeeds): void
    {
        $provider = new class($name, $succeeds) implements LLMProviderInterface {
            public function __construct(private readonly string $name, private readonly bool $succeeds)
            {
            }

            public function chat(array $messages, array $options = []): LLMResponse
            {
                if (! $this->succeeds) {
                    throw new LLMProviderRequestFailedException("{$this->name} ping failed");
                }

                return LLMResponse::success('pong', $this->name, 'test-model', 3, 1, 0.001, 15.0);
            }

            public function estimateCost(array $messages): float
            {
                return 0.001;
            }

            public function supports(LLMFeature $feature): bool
            {
                return true;
            }

            public function getName(): string
            {
                return $this->name;
            }
        };

        app(LLMProviderRegistry::class)->register($name, $provider);
    }

    public function test_testConnection_onSuccess_returnsSuccessAndLogsAdminTestConnection(): void
    {
        $this->registerFakeProvider('fake-ok', succeeds: true);
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->postJson(route('dashboard.nexus.llm-settings.test-connection'), ['provider' => 'fake-ok']);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('nexus_llm_usage_logs', [
            'provider' => 'fake-ok',
            'feature' => 'admin_test_connection',
            'business_id' => null,
            'agent_id' => null,
            'success' => 1,
        ]);
    }

    public function test_testConnection_onFailure_returnsFailureAndLogsFailedAttempt(): void
    {
        $this->registerFakeProvider('fake-down', succeeds: false);
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->postJson(route('dashboard.nexus.llm-settings.test-connection'), ['provider' => 'fake-down']);

        $response->assertStatus(200);
        $response->assertJson(['success' => false]);
        $this->assertDatabaseHas('nexus_llm_usage_logs', [
            'provider' => 'fake-down',
            'feature' => 'admin_test_connection',
            'success' => 0,
        ]);
    }

    public function test_testConnection_withUnregisteredProvider_returns404(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->postJson(route('dashboard.nexus.llm-settings.test-connection'), ['provider' => 'does-not-exist']);

        $response->assertStatus(404);
        $response->assertJson(['success' => false]);
    }

    public function test_testConnection_isNeverBudgetChecked_evenWithNoBudgetConfigured(): void
    {
        // A daily/monthly budget of 0 would make LLMBudgetGuard treat the
        // limit as unlimited anyway, so this proves the more meaningful
        // thing: the endpoint never even resolves LLMBudgetGuard — a
        // provider ping always gets a real answer, not a budget rejection.
        config([
            'nexus.platform.llm.cost_control.daily_budget_per_agent_irt' => 1,
            'nexus.platform.llm.cost_control.monthly_budget_per_business_irt' => 1,
            'nexus.platform.llm.provider_tiers.fake-expensive' => 'paid',
        ]);
        $this->registerFakeProvider('fake-expensive', succeeds: true);
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->postJson(route('dashboard.nexus.llm-settings.test-connection'), ['provider' => 'fake-expensive']);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }
}

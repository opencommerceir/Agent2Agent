<?php

namespace Tests\Feature\Dashboard;

use App\Core\Application\Actions\CreateUserAction;
use App\Core\Infrastructure\Models\User;
use App\Domains\Nexus\Admin\Application\Services\MarginSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NexusMarginSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        $data = app(CreateUserAction::class)->execute('Admin', 'admin-'.uniqid().'@example.com', 'password123', 'admin');

        return User::query()->find($data->id);
    }

    public function test_index_showsConfigDefaultsWhenNoOverrideExists(): void
    {
        config([
            'nexus.platform.margin.llm_cost_markup_percent' => 30.0,
            'nexus.platform.margin.transaction_fee_percent' => 0.5,
        ]);
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get(route('dashboard.nexus.margin-settings.index'));

        $response->assertStatus(200);
        $response->assertSee('value="30"', false);
        $response->assertSee('value="0.5"', false);
    }

    public function test_update_savesAndTakesEffectImmediately(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->put(route('dashboard.nexus.margin-settings.update'), [
            'llm_cost_markup_percent' => 35.5,
            'transaction_fee_percent' => 0.75,
            'subscription_markup_percent' => 22.0,
            'negotiation_fee_percent' => 1.5,
        ]);

        $response->assertRedirect(route('dashboard.nexus.margin-settings.index'));
        $this->assertSame(0.75, app(MarginSettingsService::class)->transactionFeePercent());
        $this->assertDatabaseHas('nexus_platform_settings', ['key' => 'transaction_fee_percent', 'value' => '0.75']);
    }

    public function test_index_guestIsRedirectedToLogin(): void
    {
        $this->get(route('dashboard.nexus.margin-settings.index'))->assertRedirect('/login');
    }
}

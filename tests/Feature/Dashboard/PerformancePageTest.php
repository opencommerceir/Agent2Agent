<?php

namespace Tests\Feature\Dashboard;

use App\Core\Application\Actions\CreateUserAction;
use App\Core\Application\Services\PerformanceMonitor;
use App\Core\Infrastructure\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 Stage 8 (Performance Optimization, §7.20) — `/dashboard/performance`,
 * the one Dashboard resource page that is deliberately not tenant-scoped
 * (PerformanceController's own docblock explains why).
 */
class PerformancePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PerformanceMonitor::class)->reset();
    }

    public function test_index_rendersTheHeadlineMetricsAndSlowQueriesSection(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get('/dashboard/performance');

        $response->assertStatus(200);
        $response->assertSee('OpenCommerce Dashboard');
        $response->assertSee(t('messages.performance.average_response_time'));
        $response->assertSee(t('messages.performance.cache_hit_rate'));
        $response->assertSee(t('messages.performance.no_slow_queries'));
    }

    public function test_index_withRecordedSlowQuery_listsIt(): void
    {
        $admin = $this->createAdmin();
        app(PerformanceMonitor::class)->recordQueryTime(150.0, 'select * from orders');

        $response = $this->actingAs($admin)->get('/dashboard/performance');

        $response->assertStatus(200);
        $response->assertSee('select * from orders');
    }

    public function test_index_guestIsRedirectedToLogin(): void
    {
        $this->get('/dashboard/performance')->assertRedirect('/login');
    }

    private function createAdmin(): User
    {
        $data = app(CreateUserAction::class)->execute('Admin', 'admin-'.uniqid().'@example.com', 'password123', 'admin');

        return User::query()->find($data->id);
    }
}

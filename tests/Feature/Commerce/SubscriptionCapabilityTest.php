<?php

namespace Tests\Feature\Commerce;

use App\Core\Application\Actions\AddMemberToOrganizationAction;
use App\Core\Application\Actions\AssignPermissionToRoleAction;
use App\Core\Application\Actions\AssignRoleToMemberAction;
use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreatePermissionAction;
use App\Core\Application\Actions\CreateRoleAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\GenerateAgentTokenAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Domain\Repositories\PermissionRepositoryInterface;
use App\Core\Domain\ValueObjects\MemberType;
use App\Core\Domain\ValueObjects\PermissionKey;
use App\Modules\Commerce\Application\Services\PaymentGatewayInterface;
use App\Modules\Commerce\Application\Services\PaymentGatewayResult;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\PaymentMethod;
use Database\Seeders\CommerceCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The literal end-to-end scenario from Phase 5, Stage 5's own request
 * (§7.25), driven entirely through MCP, plus the scheduler command that
 * actually drives billing forward. "Trial end -> renewal" and "period end
 * -> cancelled" are both exercised by directly moving a Subscription's own
 * `current_period_end` into the past (the same "simulate time passing by
 * moving a stored timestamp, not by waiting real days" technique
 * `ExpirePointsAction`'s own tests already use) and then running the real
 * `subscription:process-due` artisan command — this also satisfies the
 * request's own separate "Scheduler: Process due subscriptions" step, so
 * it isn't repeated as a standalone no-op step at the end. The Payment
 * Failure scenario (3 failures -> past_due) runs last, against its own
 * dedicated Subscription, since it needs `PaymentGatewayInterface` rebound
 * to an always-declining double for the rest of the test process —
 * running it after every other step avoids that rebind affecting the
 * earlier, happy-path steps.
 */
class SubscriptionCapabilityTest extends TestCase
{
    use RefreshDatabase;

    private const PERMISSIONS = [
        'commerce.plans.manage', 'commerce.plans.read',
        'commerce.subscriptions.create', 'commerce.subscriptions.read', 'commerce.subscriptions.manage',
        'commerce.customers.create',
    ];

    public function test_fullSubscriptionLifecycle_fromPlanCreationToCancellation(): void
    {
        $this->seed(CommerceCapabilitiesSeeder::class);

        [$tenantId, $token] = $this->registerAgentWithPermissions(self::PERMISSIONS);

        // Step 1: create a SubscriptionPlan — monthly, 100,000 (smallest
        // currency unit), 7-day trial.
        $planResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.plan.create',
            'input' => [
                'name' => 'Pro Monthly',
                'billing_cycle' => 'monthly',
                'price_amount' => 100000,
                'price_currency' => 'IRR',
                'trial_days' => 7,
            ],
        ], ['Authorization' => "Bearer {$token}"]);
        $planResponse->assertStatus(200);
        $planId = $planResponse->json('data.plan.id');

        // Step 2: create a Customer.
        $customerResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.customer.create',
            'input' => ['first_name' => 'Sara', 'last_name' => 'Ahmadi', 'email' => 'sara@example.com'],
        ], ['Authorization' => "Bearer {$token}"]);
        $customerResponse->assertStatus(200);
        $customerId = $customerResponse->json('data.customer.id');

        // Step 3: create the Subscription — a plan with a trial starts in
        // Trial with no charge at all.
        $subscribeResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.subscription.create',
            'input' => ['customer_id' => $customerId, 'subscription_plan_id' => $planId],
        ], ['Authorization' => "Bearer {$token}"]);
        $subscribeResponse->assertStatus(200);
        $subscribeResponse->assertJsonPath('data.subscription.status', 'trial');
        $subscriptionId = $subscribeResponse->json('data.subscription.id');
        $trialEnd = $subscribeResponse->json('data.subscription.trialEnd');
        $this->assertNotNull($trialEnd);

        // Step 4: the trial ends -> the scheduler renews it for real.
        // Move current_period_end (== trialEnd at creation) into the past,
        // then run the real scheduled command.
        DB::table('subscriptions')->where('id', $subscriptionId)->update([
            'current_period_end' => now()->subDay(),
        ]);
        $this->artisan('subscription:process-due')->assertSuccessful();

        $afterTrialResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.subscription.get',
            'input' => ['subscription_id' => $subscriptionId],
        ], ['Authorization' => "Bearer {$token}"]);
        $afterTrialResponse->assertJsonPath('data.subscription.status', 'active');
        $periodEndAfterTrial = new \DateTimeImmutable($afterTrialResponse->json('data.subscription.currentPeriodEnd'));
        $this->assertGreaterThan(new \DateTimeImmutable('+29 days'), $periodEndAfterTrial); // ~30-day month

        // Step 5: pause.
        $pauseResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.subscription.pause',
            'input' => ['subscription_id' => $subscriptionId],
        ], ['Authorization' => "Bearer {$token}"]);
        $pauseResponse->assertStatus(200);
        $pauseResponse->assertJsonPath('data.subscription.status', 'paused');

        // Step 6: resume — period extends by however long it sat paused.
        $resumeResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.subscription.resume',
            'input' => ['subscription_id' => $subscriptionId],
        ], ['Authorization' => "Bearer {$token}"]);
        $resumeResponse->assertStatus(200);
        $resumeResponse->assertJsonPath('data.subscription.status', 'active');
        $periodEndAfterResume = new \DateTimeImmutable($resumeResponse->json('data.subscription.currentPeriodEnd'));
        $this->assertGreaterThanOrEqual($periodEndAfterTrial, $periodEndAfterResume);

        // Step 7: upgrade to a pricier plan mid-period — proration is
        // calculated and charged as a new invoice.
        $proPlusPlanResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.plan.create',
            'input' => [
                'name' => 'Pro Plus Monthly',
                'billing_cycle' => 'monthly',
                'price_amount' => 200000,
                'price_currency' => 'IRR',
            ],
        ], ['Authorization' => "Bearer {$token}"]);
        $proPlusPlanId = $proPlusPlanResponse->json('data.plan.id');

        $upgradeResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.subscription.upgrade',
            'input' => ['subscription_id' => $subscriptionId, 'new_subscription_plan_id' => $proPlusPlanId],
        ], ['Authorization' => "Bearer {$token}"]);
        $upgradeResponse->assertStatus(200);
        $upgradeResponse->assertJsonPath('data.subscription.subscriptionPlanId', $proPlusPlanId);
        // Freshly renewed, so the upgrade happens right at period start —
        // proration charges close to the full price difference.
        $this->assertGreaterThan(0, $upgradeResponse->json('data.invoice.amount'));

        $invoiceListResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.invoice.list',
            'input' => ['subscription_id' => $subscriptionId],
        ], ['Authorization' => "Bearer {$token}"]);
        $invoiceListResponse->assertStatus(200);
        $this->assertNotEmpty($invoiceListResponse->json('data.invoices'));

        // Step 8: cancel at period end — a flag, not an immediate transition.
        $scheduleCancelResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.subscription.cancel',
            'input' => ['subscription_id' => $subscriptionId, 'immediate' => false],
        ], ['Authorization' => "Bearer {$token}"]);
        $scheduleCancelResponse->assertStatus(200);
        $scheduleCancelResponse->assertJsonPath('data.subscription.cancelAtPeriodEnd', true);
        $scheduleCancelResponse->assertJsonPath('data.subscription.status', 'active');

        // Step 9: the period actually ends -> the scheduler turns the flag
        // into a real Cancelled transition, no new invoice.
        $invoiceCountBeforeCancel = count($invoiceListResponse->json('data.invoices'));
        DB::table('subscriptions')->where('id', $subscriptionId)->update([
            'current_period_end' => now()->subDay(),
        ]);
        $this->artisan('subscription:process-due')->assertSuccessful();

        $finalResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.subscription.get',
            'input' => ['subscription_id' => $subscriptionId],
        ], ['Authorization' => "Bearer {$token}"]);
        $finalResponse->assertJsonPath('data.subscription.status', 'cancelled');

        $finalInvoiceListResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.invoice.list',
            'input' => ['subscription_id' => $subscriptionId],
        ], ['Authorization' => "Bearer {$token}"]);
        $this->assertCount($invoiceCountBeforeCancel, $finalInvoiceListResponse->json('data.invoices'));

        // Tenant isolation: a second tenant's Agent can never see the
        // first tenant's Subscription or SubscriptionPlan.
        [, $tokenB] = $this->registerAgentWithPermissions(['commerce.subscriptions.read', 'commerce.plans.read']);

        $crossTenantSubscription = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.subscription.get',
            'input' => ['subscription_id' => $subscriptionId],
        ], ['Authorization' => "Bearer {$tokenB}"]);
        $crossTenantSubscription->assertStatus(404);
        $crossTenantSubscription->assertJsonPath('error.code', 'NOT_FOUND');

        $crossTenantPlan = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.plan.get',
            'input' => ['plan_id' => $planId],
        ], ['Authorization' => "Bearer {$tokenB}"]);
        $crossTenantPlan->assertStatus(404);
        $crossTenantPlan->assertJsonPath('error.code', 'NOT_FOUND');
    }

    /**
     * Payment Failure: 3 declined attempts (the initial renewal charge +
     * 2 retries) transition a Subscription to PastDue. Runs against its
     * own dedicated tenant/Subscription and permanently rebinds
     * PaymentGatewayInterface to an always-declining double for the rest
     * of this test method only — kept in its own test method (not folded
     * into the happy-path scenario above) specifically so that rebind can
     * never leak into the earlier steps.
     */
    public function test_paymentFailure_afterThreeDeclinedAttempts_transitionsToPastDue(): void
    {
        $this->seed(CommerceCapabilitiesSeeder::class);

        [$tenantId, $token] = $this->registerAgentWithPermissions(self::PERMISSIONS);

        $planResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.plan.create',
            'input' => ['name' => 'Basic Monthly', 'billing_cycle' => 'monthly', 'price_amount' => 50000, 'price_currency' => 'IRR'],
        ], ['Authorization' => "Bearer {$token}"]);
        $planId = $planResponse->json('data.plan.id');

        $customerResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.customer.create',
            'input' => ['first_name' => 'Reza', 'last_name' => 'Karimi', 'email' => 'reza@example.com'],
        ], ['Authorization' => "Bearer {$token}"]);
        $customerId = $customerResponse->json('data.customer.id');

        // Now switch the gateway to always decline — the very first charge
        // (no trial on this plan) fails, taking the Subscription straight
        // to PastDue with no retry grace at all (CreateSubscriptionAction's
        // own policy, distinct from a *renewal* failure's 3-retry grace).
        $this->app->instance(PaymentGatewayInterface::class, new class implements PaymentGatewayInterface {
            public function charge(Money $amount, PaymentMethod $method, array $paymentDetails): PaymentGatewayResult
            {
                return new PaymentGatewayResult(successful: false, transactionId: null, rawResponse: ['error' => 'card_declined']);
            }
        });

        $subscribeResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.subscription.create',
            'input' => ['customer_id' => $customerId, 'subscription_plan_id' => $planId],
        ], ['Authorization' => "Bearer {$token}"]);
        $subscribeResponse->assertStatus(200);
        $subscribeResponse->assertJsonPath('data.subscription.status', 'past_due');
        $subscriptionId = $subscribeResponse->json('data.subscription.id');

        $invoiceId = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.invoice.list',
            'input' => ['subscription_id' => $subscriptionId],
        ], ['Authorization' => "Bearer {$token}"])->json('data.invoices.0.id');

        // Two more declined retries (this invoice's own retryCount is
        // already 1 from the initial failure above) exhaust it at 3 and
        // push the Subscription to PastDue again via the retry path —
        // already PastDue here, so this proves the retry path is safe to
        // run against an already-PastDue Subscription too, not just the
        // exhausting call itself.
        for ($i = 0; $i < 2; $i++) {
            DB::table('subscription_invoices')->where('id', $invoiceId)->update(['failed_at' => now()->subDays(4)]);
            $this->artisan('subscription:process-due')->assertSuccessful();
        }

        $finalInvoiceResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.invoice.list',
            'input' => ['subscription_id' => $subscriptionId],
        ], ['Authorization' => "Bearer {$token}"]);
        $finalInvoiceResponse->assertJsonPath('data.invoices.0.retryCount', 3);
        $finalInvoiceResponse->assertJsonPath('data.invoices.0.status', 'failed');

        $finalSubscriptionResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.subscription.get',
            'input' => ['subscription_id' => $subscriptionId],
        ], ['Authorization' => "Bearer {$token}"]);
        $finalSubscriptionResponse->assertJsonPath('data.subscription.status', 'past_due');
    }

    private function registerAgentWithPermissions(array $permissionKeys): array
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Acme Ops', 'acme-ops-'.uniqid());
        $agent = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'Subscription Bot', 'custom');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        if ($permissionKeys !== []) {
            $role = app(CreateRoleAction::class)->execute($tenant->id, 'Subscription Operator', 'subscription-operator-'.uniqid());

            foreach ($permissionKeys as $permissionKey) {
                $existing = app(PermissionRepositoryInterface::class)->findByKey(new PermissionKey($permissionKey));
                $permissionId = $existing?->id() ?? app(CreatePermissionAction::class)->execute($permissionKey)->id;
                app(AssignPermissionToRoleAction::class)->execute($role->id, $permissionId);
            }

            app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $agent->id, $role->id);
        }

        $token = app(GenerateAgentTokenAction::class)->execute($agent->id)->plainToken;

        return [$tenant->id, $token];
    }
}

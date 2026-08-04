<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5, Stage 5 (Subscription & Recurring Orders, §7.25). `status` /
 * `cancel_at_period_end` mirror Subscription's own state machine and flag
 * exactly (that entity's own docblock). The compound
 * (status, current_period_end) index backs
 * SubscriptionRepositoryInterface::findDueForRenewal()'s own daily scan —
 * the same "index the query the scheduler actually runs" reasoning
 * point_transactions' own (tenant_id, expires_at) index has (§7.20).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $table->string('status');
            $table->timestamp('current_period_start');
            $table->timestamp('current_period_end');
            $table->timestamp('trial_start')->nullable();
            $table->timestamp('trial_end')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->string('payment_method_id')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'status', 'current_period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};

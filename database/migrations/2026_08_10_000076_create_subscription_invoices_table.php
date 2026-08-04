<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5, Stage 5 (Subscription & Recurring Orders, §7.25). `order_id` is
 * nullable with a real FK (same module, unlike Shipping's own cross-module
 * `orders.shipment_id` which deliberately has none) but has no writer this
 * stage — see SubscriptionInvoice's own docblock. The
 * (tenant_id, status, failed_at) index backs
 * SubscriptionInvoiceRepositoryInterface::findDueForRetry()'s own daily
 * scan, the same reasoning the subscriptions table's own
 * (status, current_period_end) index has.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->unsignedInteger('amount');
            $table->string('currency', 3);
            $table->string('status');
            $table->timestamp('due_date');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'subscription_id']);
            $table->index(['tenant_id', 'status', 'failed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_invoices');
    }
};

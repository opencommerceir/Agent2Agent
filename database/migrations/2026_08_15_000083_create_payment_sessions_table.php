<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bridges "a redirect-based charge was started" and "the gateway
     * confirmed it" (§7.37) — provider_reference is nullable until the
     * gateway actually responds to initiate(); no unique constraint on
     * it alone since it's scoped per-gateway (two different gateways
     * could theoretically mint the same-looking reference string).
     */
    public function up(): void
    {
        Schema::create('payment_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->string('gateway');
            $table->string('provider_reference')->nullable();
            $table->unsignedBigInteger('total_amount');
            $table->unsignedBigInteger('tax_amount');
            $table->unsignedBigInteger('discount_amount');
            $table->string('currency', 3);
            $table->string('status')->default('pending');
            $table->string('coupon_code')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->text('notes')->nullable();
            $table->string('region')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['gateway', 'provider_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_sessions');
    }
};

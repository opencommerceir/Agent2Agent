<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * No FK on `order_id` into Commerce's `orders` table — the same
     * reasoning `loyalty_accounts.customer_id`/CRM's `tickets.customer_id`
     * already establish for a cross-module reference: Shipping depends
     * on Commerce's `OrderRepositoryInterface` (Dependency Inversion),
     * never a direct database-level foreign key into another module's
     * table. `shipping_method_id` DOES get a real FK, since that table
     * belongs to this same module. `tracking_number` is unique per
     * tenant, mirroring `order_number`/`invoice_number`'s own per-tenant
     * uniqueness.
     */
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('order_id');
            $table->foreignId('shipping_method_id')->constrained('shipping_methods')->cascadeOnDelete();
            $table->string('tracking_number');
            $table->string('status')->default('pending');
            $table->unsignedInteger('weight_grams');
            $table->unsignedInteger('shipping_cost_amount');
            $table->string('shipping_cost_currency', 3);
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'tracking_number']);
            $table->index(['tenant_id', 'order_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};

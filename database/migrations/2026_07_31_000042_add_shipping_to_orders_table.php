<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The first migration in this codebase where a later module (Shipping,
     * Phase 4) alters an earlier module's (Commerce, Phase 2) own table —
     * see `Order::assignShipping()`'s own docblock for the full
     * architectural reasoning. All three columns are nullable and
     * default to nothing (no `->default()`) so every Order placed before
     * this migration, and every Order Shipping never touches, is
     * completely unaffected. `shipping_method_id`/`shipment_id` are
     * plain `unsignedBigInteger` with no FK constraint — the identical
     * "cross-module reference through an Interface, not a database-level
     * FK" reasoning `shipments.order_id` has in the other direction
     * (this migration's own module, Shipping, owns both referenced
     * tables — `orders` itself must not gain a hard dependency on
     * Shipping's tables existing, the same reason `carts.owner_id` has
     * no FK either).
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('shipping_method_id')->nullable()->after('notes');
            $table->unsignedBigInteger('shipment_id')->nullable()->after('shipping_method_id');
            $table->unsignedInteger('shipping_cost_amount')->nullable()->after('shipment_id');
            $table->string('shipping_cost_currency', 3)->nullable()->after('shipping_cost_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['shipping_method_id', 'shipment_id', 'shipping_cost_amount', 'shipping_cost_currency']);
        });
    }
};

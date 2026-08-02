<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5, Stage 2 (Multi-warehouse Inventory, §7.22) — the Shipping-side
 * half of "CalculateShippingRateAction finds the nearest Warehouse and
 * prices distance". `rate_per_km` is cents-per-kilometer (Money-as-
 * integer, HANDOFF gotcha #4), nullable with no default so an *existing*
 * ShippingMethod row (created before this stage) reads as null —
 * ShippingMethod::ratePerKm() falls back to Money::fromAmount(0, ...) for
 * a null column, so every pre-existing ShippingMethod keeps costing
 * exactly what it did before this stage (a $0 distance surcharge) unless
 * an operator explicitly sets a real rate via UpdateShippingMethodAction
 * or a new one is created with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_methods', function (Blueprint $table) {
            $table->unsignedInteger('rate_per_km')->nullable()->after('rate_per_kg_amount');
        });
    }

    public function down(): void
    {
        Schema::table('shipping_methods', function (Blueprint $table) {
            $table->dropColumn('rate_per_km');
        });
    }
};

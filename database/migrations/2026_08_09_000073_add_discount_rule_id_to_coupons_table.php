<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5, Stage 4 (Advanced Discount Rules, §7.24). When set, this
 * Coupon's own `discount_type`/`discount_value` are bypassed in favor of
 * the linked DiscountRule's own (real) type/value/conditions —
 * `CalculatePricingAction`/`ProcessPaymentAction`'s own widening this
 * stage. Nullable, additive: every existing Coupon reads back with
 * `discount_rule_id` null and keeps computing its discount exactly as
 * before (`Coupon::calculateDiscount()` itself is untouched).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->foreignId('discount_rule_id')->nullable()->after('discount_value')
                ->constrained('discount_rules')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discount_rule_id');
        });
    }
};

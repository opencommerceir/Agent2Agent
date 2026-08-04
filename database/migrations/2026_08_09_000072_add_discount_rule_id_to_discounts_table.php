<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5, Stage 4 (Advanced Discount Rules, §7.24) — the exact extension
 * `Discount`'s own docblock already anticipated when `coupon_id` was made
 * nullable back in Phase 2 Stage 5 ("a future non-coupon discount source
 * can still produce a Discount row"). Nullable, additive: every existing
 * Discount row (always Coupon-sourced until this stage) reads back with
 * `discount_rule_id` null, completely unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discounts', function (Blueprint $table) {
            $table->foreignId('discount_rule_id')->nullable()->after('coupon_id')
                ->constrained('discount_rules')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('discounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discount_rule_id');
        });
    }
};

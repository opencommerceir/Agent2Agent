<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5, Stage 4 (Advanced Discount Rules, §7.24). `cart_id` is
 * non-nullable and there is no `order_id` column at all — deliberately
 * scoped to Carts only, see `AppliedDiscount`'s own docblock for the
 * full "why not also Orders" reasoning (the existing `discounts` table,
 * widened this same stage with `discount_rule_id`, already owns the
 * Order side). `discount_rule_id`/`coupon_id` are both nullable and both
 * may be null at once (a discount could in principle come from neither —
 * mirrors `discounts.coupon_id`'s own nullable precedent). Only
 * `created_at` — every `commerce.discount.apply` call deletes and
 * reinserts the whole set for a Cart, never updates a row in place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applied_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->foreignId('discount_rule_id')->nullable()->constrained('discount_rules')->nullOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->string('discount_type');
            $table->unsignedBigInteger('discount_amount');
            $table->string('discount_currency', 3);
            $table->json('applied_to');
            $table->timestamp('created_at')->nullable();

            $table->index(['tenant_id', 'cart_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applied_discounts');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5, Stage 4 (Advanced Discount Rules, §7.24). `discount_value`'s
 * meaning depends on `discount_type`, the identical one-column-many-meanings
 * shape `coupons.discount_value` already has (that migration's own
 * docblock) — a whole percent for `percentage`, cents for `fixed_amount`,
 * "how many units are free" for `buy_x_get_y`, and an ignored fallback
 * percentage for `tiered` (real tiers live in a `discount_rule_conditions`
 * row instead — see `DiscountCalculator`'s own docblock).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('discount_type');
            $table->unsignedInteger('discount_value');
            $table->unsignedInteger('priority')->default(0);
            $table->string('stackability');
            $table->timestamp('starts_at');
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_rules');
    }
};

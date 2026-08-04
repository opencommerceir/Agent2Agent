<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5, Stage 4 (Advanced Discount Rules, §7.24). No `tenant_id` of
 * its own — inherited through `discount_rule_id`, the same shape
 * `warehouse_transfer_items`/`order_items` already have. Only
 * `created_at` (no `updated_at`) — frozen at creation, the same
 * "structure fixed, generic fields aren't" shape `variant_attribute_values`
 * already has (§7.21) — there is no "edit a condition" operation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_rule_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discount_rule_id')->constrained('discount_rules')->cascadeOnDelete();
            $table->string('condition_type');
            $table->json('condition_value');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_rule_conditions');
    }
};

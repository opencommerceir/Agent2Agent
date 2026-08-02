<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5, Stage 1 (Product Variants, §7.21). No `tenant_id` of its own —
 * inherited through `attribute_id`, the same shape `order_items`/
 * `ticket_comments`/`workflow_rules` already have relative to their own
 * parent. Values are frozen after creation (no
 * UpdateVariantAttributeValueAction, no "add a value to an existing
 * attribute" operation this stage) — the same documented gap Workflows'
 * own "rules/actions frozen after creation" already has (§8.25);
 * `VariantAttribute::create()` sets every value it will ever have, all at
 * once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variant_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained('variant_attributes')->cascadeOnDelete();
            $table->string('value');
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamp('created_at')->nullable();

            $table->unique(['attribute_id', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_attribute_values');
    }
};

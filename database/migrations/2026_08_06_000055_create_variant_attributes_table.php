<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5, Stage 1 (Product Variants, §7.21). Tenant-scoped, reusable
 * across many Products — "Color"/"Size" defined once per tenant, not
 * once per Product. No `updated_at`: nothing in this stage renames an
 * attribute after creation (no UpdateVariantAttributeAction was
 * requested or built), the same "no mutator, no timestamp for it" shape
 * AnalyticsSnapshot's own single `created_at` already has.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variant_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamp('created_at')->nullable();

            $table->unique(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_attributes');
    }
};

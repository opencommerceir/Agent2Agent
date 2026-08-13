<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_automation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('type', 32);
            // Type-specific fields (counterpartyBusinessId/catalogItemId/
            // intervalDays for recurring_order, productId/thresholdQuantity
            // for inventory_alert, catalogItemId/targetPriceAmount/direction
            // for price_alert) — one JSON bag rather than a column per type,
            // the same `attributes` escape hatch Catalog's own Product/
            // Service already use for industry-specific data that doesn't
            // deserve a dedicated migration per shape.
            $table->json('config');
            $table->string('status', 16)->default('active');
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_automation_rules');
    }
};

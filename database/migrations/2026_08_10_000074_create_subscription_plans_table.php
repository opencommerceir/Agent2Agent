<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5, Stage 5 (Subscription & Recurring Orders, §7.25). `features` is
 * the request's own single JSON column at the plan level — TrialPeriod's
 * own docblock explains why no second, trial-scoped features column exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('billing_cycle');
            $table->unsignedInteger('price_amount');
            $table->string('price_currency', 3);
            $table->unsignedInteger('trial_days')->default(0);
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};

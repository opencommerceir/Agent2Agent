<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `base_rate`/`rate_per_kg` are both Money-as-Integer (cents) plus
     * their own currency column, the same paired-columns shape every
     * other Money-shaped field in this codebase already uses
     * (HANDOFF gotcha #4). No `is_active` update path exists this stage
     * (no `UpdateShippingMethodAction` was requested — Reward's own
     * "no update/deactivate method" precedent, Loyalty §7.10) but the
     * column is still `updated_at`-ready like `rewards`/`tax_rates` are.
     */
    public function up(): void
    {
        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('base_rate_amount');
            $table->string('base_rate_currency', 3);
            $table->unsignedInteger('rate_per_kg_amount');
            $table->string('rate_per_kg_currency', 3);
            $table->unsignedInteger('estimated_days_min');
            $table->unsignedInteger('estimated_days_max');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_methods');
    }
};

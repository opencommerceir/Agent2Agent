<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `discount_amount` is nullable and only meaningful when
     * `reward_type` is `discount_coupon` (Reward's own docblock) — an
     * integer, in cents, same Money-as-Integer reasoning as every other
     * currency-shaped column in this codebase (HANDOFF gotcha #4).
     */
    public function up(): void
    {
        Schema::create('rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('reward_type');
            $table->unsignedInteger('points_required');
            $table->unsignedInteger('discount_amount')->nullable();
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
        Schema::dropIfExists('rewards');
    }
};

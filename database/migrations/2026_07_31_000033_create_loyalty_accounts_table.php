<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One LoyaltyAccount per Customer per tenant (unique(tenant_id,
     * customer_id) — rule §d.2). No FK to Commerce's `customers` table:
     * Loyalty depends on Commerce's CustomerRepositoryInterface at the
     * Domain layer (Dependency Inversion), never a direct DB-level
     * foreign key into another module's table — the same reason CRM's
     * `tickets`/`customer_notes` migrations don't FK into `customers`
     * either.
     */
    public function up(): void
    {
        Schema::create('loyalty_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedInteger('total_points_earned')->default(0);
            $table->unsignedInteger('total_points_redeemed')->default(0);
            $table->unsignedInteger('current_balance')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'customer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_accounts');
    }
};

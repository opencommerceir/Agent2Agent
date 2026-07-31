<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * No `updated_at` — a Redemption is written once by RedeemPointsAction
     * and never edited (no MarkRedemptionCompleteAction/CancelRedemptionAction
     * exist this stage — `status` models `pending`/`cancelled` as real,
     * documented-but-unreached states, same "modeled but not all reachable
     * yet" shape Workflows' EventType::CartAbandoned/OrderHighValue have).
     */
    public function up(): void
    {
        Schema::create('redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('loyalty_account_id')->constrained('loyalty_accounts')->cascadeOnDelete();
            $table->foreignId('reward_id')->constrained('rewards')->cascadeOnDelete();
            $table->unsignedInteger('points_used');
            $table->string('status')->default('completed');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'loyalty_account_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('redemptions');
    }
};

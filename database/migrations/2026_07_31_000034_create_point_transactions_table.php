<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `points` is a signed integer (positive for earn/bonus, negative for
     * redeem/expire, either for adjust — PointTransaction::record()'s own
     * docblock enforces the sign-by-type invariant) — deliberately a
     * plain int, not the Points Value Object, since Points only ever
     * represents a non-negative *amount* (HANDOFF Points VO docblock).
     * `reference_id` is nullable and untyped by design — it points at
     * whatever caused this entry (an Order id for `earn`, another
     * PointTransaction's id for `expire` — see
     * PointTransactionRepositoryInterface::findExpirable()'s docblock)
     * and is never a real foreign key, since what it references varies by
     * transaction_type. No `updated_at` — a ledger entry is immutable,
     * same shape `workflow_logs`/`ticket_comments` already established.
     */
    public function up(): void
    {
        Schema::create('point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('loyalty_account_id')->constrained('loyalty_accounts')->cascadeOnDelete();
            $table->integer('points');
            $table->string('transaction_type');
            $table->string('description')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'loyalty_account_id']);
            $table->index(['loyalty_account_id', 'transaction_type', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('point_transactions');
    }
};

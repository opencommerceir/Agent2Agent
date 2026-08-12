<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Credit ledger — immutable audit trail of every balance change
     * (CLAUDE.md's "Immutable audit trail for all Agent actions", no
     * separate generic AuditLog exists or is needed for this). No
     * updated_at, same shape workflow_logs already documents for an
     * immutable log table.
     */
    public function up(): void
    {
        Schema::create('nexus_credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('type');
            $table->integer('amount');
            $table->string('reason');
            $table->integer('balance_after');
            $table->unsignedBigInteger('related_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['business_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_credit_transactions');
    }
};

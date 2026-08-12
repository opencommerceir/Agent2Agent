<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6/M4 — the immutable audit trail for Business::suspend()/
 * reactivate(), same "no generic AuditLog, a domain-specific ledger
 * instead" shape CreditTransaction/LLMUsageLog already establish. No
 * `updated_at` (immutable ledger row, never edited).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_suspension_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('action');
            $table->string('reason');
            $table->string('triggered_by');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['business_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_suspension_records');
    }
};

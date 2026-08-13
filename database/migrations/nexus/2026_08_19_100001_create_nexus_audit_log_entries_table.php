<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 7/M9 — the first platform-wide, cross-domain audit ledger in
     * this codebase (see AuditLogEntry's own docblock for why this is a
     * deliberate reversal of the "no generic AuditLog" restraint every
     * domain-specific ledger, CreditTransaction included, has documented
     * since Phase 3/M1). No updated_at — immutable, same shape as every
     * other ledger table here.
     *
     * sequence/entry_hash are both unique: sequence enforces the chain
     * has no gaps or duplicates, entry_hash enforces no two entries ever
     * hash identically (would only happen if the chain were broken and
     * replayed).
     */
    public function up(): void
    {
        Schema::create('nexus_audit_log_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sequence')->unique();
            $table->string('prev_hash', 64);
            $table->string('entry_hash', 64)->unique();
            $table->string('capability_name');
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->unsignedBigInteger('core_agent_id')->nullable();
            $table->string('status');
            $table->json('input_summary');
            $table->unsignedInteger('execution_time_ms');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['business_id', 'created_at']);
            $table->index(['capability_name', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_audit_log_entries');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agent Orchestrator, Phase 6 Stage 4 — Execution Memory & Learning
 * (§7.29). One row per (tenant, goal_pattern, agent_type) — a repeat
 * occurrence of the same pattern updates its own row's
 * usage_count/success_rate/last_used_at rather than inserting a second one
 * (`EloquentExecutionPatternRepository::save()`'s own upsert-by-composite-key
 * docblock); no unique constraint is declared for it, since
 * `findExisting()` is always consulted first and this table has exactly
 * one writer (`LearnFromExecutionListener`).
 *
 * Deliberately *not* accompanied by a second `execution_memories` table —
 * `agent_executions`/`agent_execution_steps` (§7.26, the previous
 * migration pair) already persist every finished Goal execution in full;
 * this table only stores what's genuinely new this stage, the *derived*
 * pattern learned from that history. See `docs/execution-memory.md`'s own
 * "Why no new ExecutionMemory entity" section.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('execution_patterns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('goal_pattern');
            $table->string('agent_type');
            $table->json('successful_capabilities');
            $table->json('failed_capabilities');
            $table->unsignedInteger('usage_count')->default(1);
            $table->float('success_rate')->default(1.0);
            $table->timestamp('last_used_at');
            $table->timestamps();

            $table->index(['tenant_id', 'goal_pattern', 'agent_type']);
            $table->index(['tenant_id', 'agent_type', 'success_rate']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_patterns');
    }
};

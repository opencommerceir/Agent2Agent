<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agent Orchestrator, Phase 6 Stage 5 — Multi-Agent Collaboration (§7.30).
 * One row per `agent.collaboration.delegate` call — the work-tracking
 * sibling to `agent_messages`' own append-only communication log; see
 * `DelegationRequest`'s own docblock for the full split.
 *
 * `result` carries either the delegated goal's own successful
 * `ExecutionResultData::toArray()`, or `{"error": "..."}` on failure/
 * timeout — one JSON column for both outcomes, matching the request's own
 * schema (no separate error column).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delegation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('parent_execution_id')->nullable();
            $table->string('from_agent_type');
            $table->string('to_agent_type');
            $table->text('task');
            $table->unsignedTinyInteger('priority')->default(5);
            $table->unsignedInteger('timeout_seconds')->default(30);
            $table->string('status');
            $table->json('result')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'from_agent_type']);
            $table->index(['tenant_id', 'to_agent_type']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delegation_requests');
    }
};

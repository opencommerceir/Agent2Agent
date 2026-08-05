<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agent Orchestrator, Phase 6 Stage 5 — Multi-Agent Collaboration (§7.30).
 * A durable log of one persona-to-persona communication —
 * `AgentCommunicationService` writes one `delegation`-type row and one
 * `response`-type row around every real delegation
 * (`agent.collaboration.delegate`).
 *
 * `parent_execution_id` has no FK constraint and no `agent_executions`
 * reference is enforced — it's always null this stage (a delegation
 * happens mid-plan, before the parent's own `Execution` row is persisted
 * at all — see `DelegationRequest`'s own docblock), kept nullable and
 * unconstrained for a future stage where it becomes populated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('from_agent_type');
            $table->string('to_agent_type');
            $table->string('message_type');
            $table->json('content');
            $table->string('status');
            $table->unsignedBigInteger('parent_execution_id')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'to_agent_type', 'created_at']);
            $table->index(['tenant_id', 'from_agent_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_messages');
    }
};

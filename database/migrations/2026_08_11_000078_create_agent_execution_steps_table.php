<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agent Orchestrator (§7.26). Owned by `agent_executions` the same
 * "repository owns its child records" shape WorkflowLog/InvoiceItem/
 * OrderItem already establish (HANDOFF §3) — no independent
 * `ExecutionMemoryRepositoryInterface` method looks one up by its own id,
 * only ever read back as part of the parent Execution
 * (ExecutionMemoryRepositoryInterface::findById() eager-loads them).
 * `output`/`error_message` are mutually exclusive in practice (a step is
 * either Completed with an output or Failed with an error), never
 * enforced at the schema level — the Domain entity's own state machine
 * (ExecutionStep::markAsCompleted()/markAsFailed()) is what actually
 * guarantees this.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_execution_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_execution_id')->constrained('agent_executions')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('capability');
            $table->json('input');
            $table->string('priority');
            $table->string('status');
            $table->json('output')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['agent_execution_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_execution_steps');
    }
};

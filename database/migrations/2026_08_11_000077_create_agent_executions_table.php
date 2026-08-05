<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agent Orchestrator (§7.26). One row per ExecuteGoalAction run — the
 * frozen historical record of a Goal + its resolved ExecutionPlan outcome,
 * the same "parent definition, child result" split Reporting's own
 * Report/ReportResult (§7.11) and Workflows' Workflow/WorkflowLog (§7.9)
 * already establish, here collapsed into a single row since a Goal run
 * has no separate "definition vs. result" identity worth splitting (a
 * Goal is never re-run against a later date range the way a Report is).
 *
 * `agent_type` is stored as the caller-supplied classification (ceo,
 * sales, support, finance) — informational/routing metadata only; the
 * MVP DeterministicPlanner keys off the Goal's own text, not this column
 * (see DeterministicPlanner's own docblock).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->string('agent_type');
            $table->text('goal_text');
            $table->string('status');
            $table->text('summary');
            $table->unsignedInteger('execution_time_ms');
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'agent_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_executions');
    }
};

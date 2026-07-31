<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Unlike workflow_rules/workflow_actions, this DOES carry its own
     * tenant_id (as the request's own schema specifies) rather than
     * relying solely on workflow_id — a log is a first-class audit
     * record queried directly by tenant (`workflow.log.list` accepts an
     * optional workflow_id, meaning "all of this tenant's logs" is a
     * real, supported query on its own). No updated_at — logs are
     * immutable (WorkflowLog Model's own docblock).
     */
    public function up(): void
    {
        Schema::create('workflow_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->json('event_data');
            $table->json('actions_executed');
            $table->string('status');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'workflow_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_logs');
    }
};

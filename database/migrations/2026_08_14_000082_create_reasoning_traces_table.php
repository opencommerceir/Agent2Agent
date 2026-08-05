<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reasoning_traces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('execution_id');
            $table->string('agent_type');
            $table->text('goal_text');
            $table->string('reasoning_type'); // pre_execution, post_execution
            $table->json('thoughts');
            $table->json('alternatives')->nullable();
            $table->float('confidence_score');
            $table->text('decision');
            $table->text('explanation');
            $table->timestamp('created_at');

            $table->index(['tenant_id', 'execution_id']);
            $table->index(['tenant_id', 'agent_type', 'reasoning_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reasoning_traces');
    }
};

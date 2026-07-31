<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * No tenant_id column — tenant isolation is inherited through
     * workflow_id (same shape order_items/ticket_comments/invoice_items
     * already have relative to their own parent). No updated_at — rules
     * are immutable (WorkflowRule Model's own docblock).
     */
    public function up(): void
    {
        Schema::create('workflow_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $table->string('condition_type');
            $table->string('field');
            $table->integer('threshold_value');
            $table->timestamp('created_at')->useCurrent();

            $table->index('workflow_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_rules');
    }
};

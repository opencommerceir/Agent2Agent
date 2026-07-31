<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * No tenant_id column, no updated_at — same reasoning
     * workflow_rules's own migration gives.
     */
    public function up(): void
    {
        Schema::create('workflow_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $table->string('action_type');
            $table->json('parameters')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('workflow_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_actions');
    }
};

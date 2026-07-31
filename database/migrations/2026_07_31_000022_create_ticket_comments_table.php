<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * No tenant_id column — tenant isolation for a comment is inherited
     * through ticket_id (AddCommentToTicketAction loads the parent Ticket
     * with an explicit tenantId first), the same shape order_items takes
     * relative to orders. ticket_id cascades: a comment has no meaning
     * without its Ticket. No updated_at — comments are immutable
     * (TicketComment Model's own docblock).
     */
    public function up(): void
    {
        Schema::create('ticket_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('agents');
            $table->text('content');
            $table->timestamp('created_at')->useCurrent();

            $table->index('ticket_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_comments');
    }
};

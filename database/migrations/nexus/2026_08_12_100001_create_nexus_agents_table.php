<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            // Nullable + nullOnDelete: the Core Agent/AgentToken pair is
            // provisioned by CreateAgentForBusinessAction in the same
            // request, but this row's own lifecycle must not be forced to
            // match Core's agents table 1:1 forever (e.g. a future token
            // rotation flow revoking and re-issuing a new Core Agent).
            $table->foreignId('core_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->string('name_fa');
            $table->string('name_en');
            $table->text('personality')->nullable();
            $table->string('tone')->nullable();
            $table->json('authority_limits')->nullable();
            $table->json('strategies')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_agents');
    }
};

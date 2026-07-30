<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The original `agents` table (Phase 1) stored a single `token_hash`
     * directly on the agent. That doesn't allow rotation or multiple
     * live credentials per agent, so token storage moves into its own
     * table here and the now-redundant columns are dropped from `agents`.
     */
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropUnique(['token_hash']);
            $table->dropColumn(['token_hash', 'last_used_at']);
        });

        Schema::create('agent_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('label')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_tokens');

        Schema::table('agents', function (Blueprint $table) {
            $table->string('token_hash')->unique()->after('type');
            $table->timestamp('last_used_at')->nullable();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_approval_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_request_id')->constrained('nexus_approval_requests')->cascadeOnDelete();
            $table->unsignedInteger('level_index');
            $table->string('role_required', 16);
            $table->foreignId('decided_by_owner_id')->constrained('business_owners')->cascadeOnDelete();
            $table->string('decision', 16);
            $table->timestamp('decided_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_approval_decisions');
    }
};

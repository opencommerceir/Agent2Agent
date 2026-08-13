<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negotiation_id')->unique()->constrained('negotiations')->cascadeOnDelete();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->json('required_levels');
            $table->unsignedInteger('current_level_index')->default(0);
            $table->string('status', 16)->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_approval_requests');
    }
};

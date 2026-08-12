<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_coalition_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coalition_id')->constrained('nexus_coalitions')->cascadeOnDelete();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamp('joined_at');

            $table->unique(['coalition_id', 'business_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_coalition_members');
    }
};

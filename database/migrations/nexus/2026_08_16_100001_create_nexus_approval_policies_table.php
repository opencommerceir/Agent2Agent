<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_approval_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained('businesses')->cascadeOnDelete();
            $table->json('levels');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_approval_policies');
    }
};

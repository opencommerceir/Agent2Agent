<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_credit_purchase_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('gateway');
            $table->string('provider_reference')->nullable();
            $table->string('package');
            $table->integer('total_amount');
            $table->string('total_currency');
            $table->string('status')->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['gateway', 'provider_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_credit_purchase_sessions');
    }
};

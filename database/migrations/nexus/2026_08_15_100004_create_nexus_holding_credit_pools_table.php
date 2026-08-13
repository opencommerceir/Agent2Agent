<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_holding_credit_pools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('holding_id')->unique()->constrained('nexus_holdings')->cascadeOnDelete();
            $table->integer('balance')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_holding_credit_pools');
    }
};

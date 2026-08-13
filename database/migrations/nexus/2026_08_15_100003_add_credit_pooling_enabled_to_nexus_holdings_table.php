<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nexus_holdings', function (Blueprint $table) {
            $table->boolean('credit_pooling_enabled')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('nexus_holdings', function (Blueprint $table) {
            $table->dropColumn('credit_pooling_enabled');
        });
    }
};

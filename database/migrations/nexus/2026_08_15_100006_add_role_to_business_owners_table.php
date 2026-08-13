<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_owners', function (Blueprint $table) {
            $table->string('role', 16)->default('owner')->after('business_id');
            $table->boolean('must_change_password')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('business_owners', function (Blueprint $table) {
            $table->dropColumn(['role', 'must_change_password']);
        });
    }
};

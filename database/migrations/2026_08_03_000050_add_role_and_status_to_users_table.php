<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 Stage 5 (Admin Dashboard + Human Auth) — additive to the
 * `users` table Laravel's own default scaffold created in Phase 1
 * (0001_01_01_000000_create_users_table.php). `role` backs `UserRole`
 * (admin/operator, default 'operator' so a bulk-inserted row never
 * silently becomes an admin); `is_active` backs `UserStatus` as a plain
 * boolean rather than a second string enum column, since User only ever
 * has two states (unlike Tenant's 3-state `status` column).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('operator')->after('password');
            $table->boolean('is_active')->default(true)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'is_active']);
        });
    }
};

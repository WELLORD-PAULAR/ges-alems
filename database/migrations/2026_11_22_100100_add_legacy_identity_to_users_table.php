<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The old `user_account` table becomes the Laravel `users` table. Slice 2
 * builds the login flow on these columns; Slice 1 only adds the storage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 100)->nullable()->unique()->after('name');
            $table->string('role', 20)->default('teacher')->index()->after('email');
            $table->boolean('is_active')->default(true)->index()->after('role');
            $table->string('legacy_key')->nullable()->unique()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropUnique(['legacy_key']);
            $table->dropIndex(['role']);
            $table->dropIndex(['is_active']);
            $table->dropColumn(['username', 'role', 'is_active', 'legacy_key']);
        });
    }
};

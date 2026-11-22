<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sections, subjects and teachers did not exist in the old database file; the
 * old app created them at runtime. These tables follow the columns used by
 * `classes/models/{Section,Subject,Teacher}.php`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('grade_level', 2);
            $table->string('school_year', 9);
            $table->string('legacy_key')->nullable()->unique();
            $table->timestamps();

            $table->unique(['name', 'school_year']);
            $table->index(['school_year', 'grade_level']);
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->unsignedTinyInteger('offered_term')->nullable();
            $table->string('legacy_key')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('employee_no', 30)->nullable()->unique();
            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->string('middle_name', 50)->nullable();
            $table->string('legacy_key')->nullable()->unique();
            $table->timestamps();

            $table->index(['last_name', 'first_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teachers');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('sections');
    }
};

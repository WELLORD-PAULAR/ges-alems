<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Decision D1: student identity lives in `students`, one row per person.
 * Each school year gets an `enrollments` row, so a learner can return.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('legacy_key')->nullable()->unique();
            $table->string('psa_bcn', 20)->nullable();
            $table->string('last_name', 50);
            $table->string('first_name', 50);
            $table->string('middle_name', 50)->nullable();
            $table->string('extension', 10)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('sex', 6)->nullable();
            $table->string('place_of_birth', 100)->nullable();
            $table->foreignId('mother_tongue_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('religion_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('indigenous_group_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['last_name', 'first_name', 'birth_date']);
            $table->index('psa_bcn');
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->string('legacy_key')->nullable()->unique();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('school_year', 9);
            $table->string('grade_level', 2);
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->string('lrn', 12)->nullable();
            $table->string('learning_classification', 20)->nullable();
            $table->string('last_grade_level_completed', 10)->nullable();
            $table->string('last_school_year_completed', 20)->nullable();
            $table->string('last_school_attended', 100)->nullable();
            $table->string('last_school_id', 10)->nullable();
            $table->string('attended_early_learning_program_name', 100)->nullable();
            $table->string('four_ps_household_number', 30)->nullable();
            $table->json('learning_modality')->nullable();
            $table->string('verification', 20)->default('PROCESSING')->index();
            $table->foreignId('encoded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'school_year']);
            $table->index(['school_year', 'grade_level']);
            $table->index(['school_year', 'section_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('students');
    }
};

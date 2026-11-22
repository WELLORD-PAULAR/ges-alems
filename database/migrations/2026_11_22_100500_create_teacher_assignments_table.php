<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The old `teacher_class_roster` table. The unique index is the rule the old
 * `TeacherAssignmentHandler::exists()` enforced by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->string('school_year', 9);
            $table->unsignedTinyInteger('term');
            $table->string('legacy_key')->nullable()->unique();
            $table->timestamps();

            $table->unique(
                ['teacher_id', 'subject_id', 'section_id', 'school_year', 'term'],
                'teacher_assignments_unique',
            );
            $table->index(['school_year', 'term']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_assignments');
    }
};

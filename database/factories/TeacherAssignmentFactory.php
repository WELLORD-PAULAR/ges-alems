<?php

namespace Database\Factories;

use App\Enums\Term;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeacherAssignment>
 */
class TeacherAssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'teacher_id' => Teacher::factory(),
            'subject_id' => Subject::factory(),
            'section_id' => Section::factory(),
            'school_year' => config('school.current_school_year'),
            'term' => fake()->randomElement(Term::assignable()),
        ];
    }
}

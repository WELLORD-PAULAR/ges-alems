<?php

namespace Database\Factories;

use App\Enums\GradeLevel;
use App\Enums\VerificationStatus;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'school_year' => config('school.current_school_year'),
            'grade_level' => fake()->randomElement(GradeLevel::cases()),
            'section_id' => null,
            'lrn' => fake()->optional()->numerify('############'),
            'learning_classification' => 'GRADED',
            'learning_modality' => [fake()->randomElement([
                'BLENDED (COMBINATION)',
                'HOMESCHOOLING',
                'MODULAR (PRINT)',
                'MODULAR (DIGITAL)',
                'ONLINE',
            ])],
            'verification' => VerificationStatus::Processing,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'verification' => VerificationStatus::Verified,
        ]);
    }

    public function forSchoolYear(string $schoolYear): static
    {
        return $this->state(fn (array $attributes): array => [
            'school_year' => $schoolYear,
        ]);
    }
}

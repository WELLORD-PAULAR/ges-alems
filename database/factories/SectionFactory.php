<?php

namespace Database\Factories;

use App\Enums\GradeLevel;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Section>
 */
class SectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Section '.fake()->unique()->numerify('####'),
            'grade_level' => fake()->randomElement(GradeLevel::cases()),
            'school_year' => config('school.current_school_year'),
        ];
    }

    public function forGrade(GradeLevel $grade): static
    {
        return $this->state(fn (array $attributes): array => [
            'grade_level' => $grade,
        ]);
    }

    public function forSchoolYear(string $schoolYear): static
    {
        return $this->state(fn (array $attributes): array => [
            'school_year' => $schoolYear,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Enums\Sex;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'psa_bcn' => fake()->optional()->numerify('#############'),
            'last_name' => fake()->lastName(),
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->optional()->lastName(),
            'extension' => fake()->optional(0.1)->randomElement(['Jr.', 'Sr.', 'III']),
            'birth_date' => fake()->dateTimeBetween('-16 years', '-5 years')->format('Y-m-d'),
            'sex' => fake()->randomElement(Sex::cases()),
            'place_of_birth' => fake()->city(),
        ];
    }

    public function male(): static
    {
        return $this->state(fn (array $attributes): array => [
            'sex' => Sex::Male,
        ]);
    }

    public function female(): static
    {
        return $this->state(fn (array $attributes): array => [
            'sex' => Sex::Female,
        ]);
    }
}

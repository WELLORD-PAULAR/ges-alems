<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\EnrollmentParent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnrollmentParent>
 */
class EnrollmentParentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $data = [
            'enrollment_id' => Enrollment::factory(),
            'emergency_contact' => fake()->randomElement(['FATHER', 'MOTHER', 'GUARDIAN']),
        ];

        foreach (['father', 'mother', 'guardian'] as $role) {
            $data[$role.'_last_name'] = fake()->lastName();
            $data[$role.'_first_name'] = fake()->firstName();
            $data[$role.'_middle_name'] = fake()->optional()->lastName();
            $data[$role.'_contact_number'] = fake()->numerify('09#########');
            $data[$role.'_occupation'] = fake()->jobTitle();
            $data[$role.'_relationship_status'] = fake()->randomElement(['Married', 'Separated', 'Single']);
            $data[$role.'_communication'] = fake()->randomElement(['SMS', 'Call', 'Messenger']);
        }

        return $data;
    }
}

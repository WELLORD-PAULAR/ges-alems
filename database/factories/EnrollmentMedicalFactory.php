<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\EnrollmentMedical;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnrollmentMedical>
 */
class EnrollmentMedicalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'medical_conditions' => [],
            'chronic_conditions' => [],
            'exposed_to_covid' => false,
            'pertinent_information' => fake()->optional()->sentence(),
        ];
    }
}

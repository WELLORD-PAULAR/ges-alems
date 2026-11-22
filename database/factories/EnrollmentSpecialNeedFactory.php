<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\EnrollmentSpecialNeed;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnrollmentSpecialNeed>
 */
class EnrollmentSpecialNeedFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'diagnosis' => ['NONE'],
            'manifestations' => ['NONE'],
            'has_pwd_id' => false,
        ];
    }
}

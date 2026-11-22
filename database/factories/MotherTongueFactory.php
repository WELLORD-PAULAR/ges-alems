<?php

namespace Database\Factories;

use App\Models\MotherTongue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MotherTongue>
 */
class MotherTongueFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()),
        ];
    }
}

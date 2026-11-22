<?php

namespace Database\Factories;

use App\Models\Religion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Religion>
 */
class ReligionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement([
                'Roman Catholic',
                'Iglesia ni Cristo',
                'Seventh-day Adventist',
                'Islam',
                'Protestant',
                'Jehovah\'s Witness',
                'Buddhism',
            ]).' '.fake()->unique()->numerify('##'),
        ];
    }
}

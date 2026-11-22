<?php

namespace Database\Factories;

use App\Models\IndigenousGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IndigenousGroup>
 */
class IndigenousGroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word().' '.fake()->word()),
        ];
    }
}

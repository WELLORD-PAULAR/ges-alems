<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\EnrollmentAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnrollmentAddress>
 */
class EnrollmentAddressFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $address = [
            'house_number' => fake()->buildingNumber(),
            'street_name' => fake()->streetName(),
            'barangay' => 'Barangay '.fake()->numberBetween(1, 20),
            'municipality' => fake()->city(),
            'province' => fake()->randomElement([
                'Benguet', 'Mountain Province', 'Ifugao', 'Kalinga',
                'Apayao', 'Abra', 'La Union', 'Pangasinan',
            ]),
            'country' => 'Philippines',
            'zip_code' => fake()->numerify('####'),
            'address_status' => fake()->randomElement(['Rental', 'Owned', 'Living With Relatives', 'Inherited']),
        ];

        $data = ['enrollment_id' => Enrollment::factory()];

        foreach (['current', 'permanent'] as $prefix) {
            foreach ($address as $key => $value) {
                $data[$prefix.'_'.$key] = $value;
            }
        }

        return $data;
    }
}

<?php

namespace Database\Factories;

use App\Enums\EmployerStatus;
use App\Models\EmployerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployerProfile>
 */
class EmployerProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->employer(),
            'company_name' => fake()->company(),
            'contact_person' => fake()->name(),
            'phone' => fake()->numerify('+91##########'),
            'website' => fake()->url(),
            'city' => fake()->city(),
            'status' => EmployerStatus::Approved,
            'approved_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => EmployerStatus::Pending,
            'approved_at' => null,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => EmployerStatus::Approved,
            'approved_at' => now(),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => [
            'status' => EmployerStatus::Suspended,
            'approved_at' => null,
        ]);
    }
}

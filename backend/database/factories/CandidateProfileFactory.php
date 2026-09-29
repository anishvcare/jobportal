<?php

namespace Database\Factories;

use App\Models\CandidateProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CandidateProfile>
 */
class CandidateProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->candidate(),
            'full_name' => fake()->name(),
            'dob' => fake()->dateTimeBetween('-45 years', '-20 years')->format('Y-m-d'),
            'gender' => 'male',
            'phone' => fake()->numerify('+91##########'),
            'city' => fake()->city(),
            'wizard_step' => 0,
        ];
    }

    public function withConsent(): static
    {
        return $this->state(fn () => [
            'consent_at' => now(),
            'consent_version' => config('nexus.consent_version', '1.0'),
        ]);
    }

    public function withPassport(): static
    {
        return $this->state(fn () => [
            'has_passport' => true,
            'passport_number' => 'A1234567',
            'passport_expiry' => now()->addYears(8)->format('Y-m-d'),
        ]);
    }
}

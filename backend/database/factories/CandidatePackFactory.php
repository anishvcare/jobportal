<?php

namespace Database\Factories;

use App\Models\CandidatePack;
use App\Models\CandidateProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CandidatePack>
 */
class CandidatePackFactory extends Factory
{
    public function definition(): array
    {
        return [
            'candidate_profile_id' => CandidateProfile::factory(),
            'disk' => 'documents',
            'path' => null,
            'fingerprint' => null,
            'status' => CandidatePack::STATUS_PENDING,
            'generated_at' => null,
            'error' => null,
        ];
    }

    public function ready(): static
    {
        return $this->state(fn () => [
            'status' => CandidatePack::STATUS_READY,
            'path' => 'packs/'.fake()->uuid().'.pdf',
            'fingerprint' => fake()->sha256(),
            'generated_at' => now(),
        ]);
    }
}

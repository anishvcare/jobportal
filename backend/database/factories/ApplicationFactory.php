<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\JobPost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'job_post_id' => JobPost::factory(),
            'candidate_profile_id' => CandidateProfile::factory(),
            'status' => ApplicationStatus::Applied,
            'cover_note' => fake()->optional()->sentence(),
        ];
    }

    public function shortlisted(): static
    {
        return $this->state(fn () => ['status' => ApplicationStatus::Shortlisted]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => ApplicationStatus::Rejected]);
    }

    public function selected(): static
    {
        return $this->state(fn () => ['status' => ApplicationStatus::Selected]);
    }
}

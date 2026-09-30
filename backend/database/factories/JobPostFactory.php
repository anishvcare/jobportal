<?php

namespace Database\Factories;

use App\Models\EmployerProfile;
use App\Models\JobCategory;
use App\Models\JobPost;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<JobPost>
 */
class JobPostFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->jobTitle();

        return [
            'employer_profile_id' => EmployerProfile::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 999999),
            'description' => fake()->paragraphs(3, true),
            'job_category_id' => JobCategory::factory(), // a trade
            'city' => fake()->city(),
            'vacancies' => fake()->numberBetween(1, 10),
            'published_at' => now(),
            'is_hidden' => false,
            'closed_at' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['published_at' => null]);
    }

    public function unpublished(): static
    {
        return $this->draft();
    }

    public function hidden(): static
    {
        return $this->state(fn () => ['is_hidden' => true]);
    }

    public function closed(): static
    {
        return $this->state(fn () => ['closed_at' => now()]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['deadline' => now()->subDay()->toDateString()]);
    }
}

<?php

namespace Database\Factories;

use App\Models\JobCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Produces a trade category (parent_id set to a group). Groups are the
 * top-level "Skilled"/"Unskilled" nodes; jobs and candidates use trades.
 *
 * @extends Factory<JobCategory>
 */
class JobCategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->jobTitle();

        return [
            'parent_id' => JobCategory::factory()->group(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999999),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * A top-level group node (e.g. "Skilled").
     */
    public function group(): static
    {
        return $this->state(function () {
            $name = fake()->unique()->words(2, true);

            return [
                'parent_id' => null,
                'name' => Str::title($name),
                'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999999),
            ];
        });
    }
}

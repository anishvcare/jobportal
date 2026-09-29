<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'google_id' => (string) fake()->unique()->numberBetween(10 ** 15, 10 ** 16),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'avatar_url' => null,
            'remember_token' => Str::random(10),
        ];
    }

    public function role(?Role $role): static
    {
        return $this->afterMaking(fn (User $user) => $user->role = $role);
    }

    public function candidate(): static
    {
        return $this->role(Role::Candidate);
    }

    public function employer(): static
    {
        return $this->role(Role::Employer);
    }

    public function admin(): static
    {
        return $this->role(Role::Admin);
    }
}

<?php

namespace Database\Factories;

use App\Models\ParentGuardian;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParentGuardian>
 */
class ParentGuardianFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->name(),
            'phone' => fake()->numerify('08##########'),
        ];
    }

    /**
     * Parent entered as student master data, before an account is provisioned.
     */
    public function withoutAccount(): static
    {
        return $this->state(fn (): array => ['user_id' => null]);
    }
}
